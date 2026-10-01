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
use Psr\Log\NullLogger;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;
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
    /**
     * @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository
     *
     * @deprecated the $paymentManager, $authorizeClientApi, $orderDetailsApi and $stateMachine arguments are
     *             deprecated since Sylius/PayPalPlugin 2.1 and will be removed in Sylius/PayPalPlugin 3.0.
     *             Pass a $paymentSettlementProcessor instead.
     */
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly ?ObjectManager $paymentManager = null,
        private readonly ?CacheAuthorizeClientApiInterface $authorizeClientApi = null,
        private readonly ?OrderDetailsApiInterface $orderDetailsApi = null,
        private readonly ?StateMachineInterface $stateMachine = null,
        private readonly ?PaymentSettlementProcessorInterface $paymentSettlementProcessor = null,
    ) {
        parent::__construct();

        foreach ([
            ObjectManager::class => $this->paymentManager,
            CacheAuthorizeClientApiInterface::class => $this->authorizeClientApi,
            OrderDetailsApiInterface::class => $this->orderDetailsApi,
            StateMachineInterface::class => $this->stateMachine,
        ] as $class => $argument) {
            if (null !== $argument) {
                trigger_deprecation(
                    'sylius/paypal-plugin',
                    '2.1',
                    'Passing an instance of "%s" to "%s" constructor is deprecated and will be prohibited in 3.0.',
                    $class,
                    self::class,
                );
            }
        }

        if (null === $this->paymentSettlementProcessor) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of "%s" to "%s" constructor is deprecated and will be required in 3.0.',
                PaymentSettlementProcessorInterface::class,
                self::class,
            );
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paymentSettlementProcessor = $this->paymentSettlementProcessor ?? $this->buildPaymentSettlementProcessor();

        if (null === $paymentSettlementProcessor) {
            $output->writeln(sprintf(
                '<error>No %s was given, so no payment can be settled.</error>',
                PaymentSettlementProcessorInterface::class,
            ));

            return Command::FAILURE;
        }

        $payments = $this->paymentRepository->findBy(['state' => PaymentInterface::STATE_PROCESSING]);

        /** @var PaymentInterface $payment */
        foreach ($payments as $payment) {
            if (!$this->isPayPalPayment($payment)) {
                continue;
            }

            $paymentSettlementProcessor->settle($payment);
        }

        return Command::SUCCESS;
    }

    private function buildPaymentSettlementProcessor(): ?PaymentSettlementProcessorInterface
    {
        if (
            null === $this->authorizeClientApi ||
            null === $this->orderDetailsApi ||
            null === $this->stateMachine ||
            null === $this->paymentManager
        ) {
            return null;
        }

        return new PayPalPaymentSettlementProcessor(
            $this->authorizeClientApi,
            $this->orderDetailsApi,
            $this->stateMachine,
            $this->paymentManager,
            new NullLogger(),
        );
    }

    private function isPayPalPayment(PaymentInterface $payment): bool
    {
        $paymentMethod = $payment->getMethod();
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            return false;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return
            $gatewayConfig instanceof GatewayConfigInterface &&
            $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ;
    }
}
