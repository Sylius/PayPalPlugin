<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Console\Command;

use Doctrine\Persistence\ObjectManager;
use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sylius-paypal:complete-payments',
    description: 'Completes payments for completed PayPal orders',
)]
final class CompletePaidPaymentsCommand extends Command
{
    /** @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository */
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly ObjectManager $paymentManager,
        private readonly CacheAuthorizeClientApiInterface $authorizeClientApi,
        private readonly OrderDetailsApiInterface $orderDetailsApi,
        private readonly StateMachineInterface $stateMachine,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payments = $this->paymentRepository->findBy(['state' => PaymentInterface::STATE_PROCESSING]);
        /** @var PaymentInterface $payment */
        foreach ($payments as $payment) {
            /** @var PaymentMethodInterface $paymentMethod */
            $paymentMethod = $payment->getMethod();
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            if ($gatewayConfig->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
                continue;
            }

            /** @var string $payPalOrderId */
            $payPalOrderId = $payment->getDetails()['paypal_order_id'];

            $token = $this->authorizeClientApi->authorize($paymentMethod);
            $details = $this->orderDetailsApi->get($token, $payPalOrderId);

            // When the PayPal API call fails (e.g. 404 RESOURCE_NOT_FOUND once an
            // uncaptured order has expired after ~3h), PayPalClient::request()
            // silently returns the error payload instead of throwing, so the array
            // has no "status" key. Skip those payments — an error has already been
            // logged inside the client — rather than emitting a PHP warning on
            // every hourly cron tick forever.
            if (!isset($details['status'])) {
                continue;
            }

            if ($details['status'] === 'COMPLETED') {
                $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

                $paymentDetails = $payment->getDetails();
                $paymentDetails['status'] = StatusAction::STATUS_COMPLETED;

                $payment->setDetails($paymentDetails);
            }
        }

        $this->paymentManager->flush();

        return Command::SUCCESS;
    }
}
