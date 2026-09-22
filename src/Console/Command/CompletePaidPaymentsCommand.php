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

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
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
        private readonly PaymentSettlementProcessorInterface $paymentSettlementProcessor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payments = $this->paymentRepository->findBy(['state' => PaymentInterface::STATE_PROCESSING]);

        /** @var PaymentInterface $payment */
        foreach ($payments as $payment) {
            if (!$this->isPayPalPayment($payment)) {
                continue;
            }

            $this->paymentSettlementProcessor->settle($payment);
        }

        return Command::SUCCESS;
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
