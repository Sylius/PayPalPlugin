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

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;

final readonly class PayPalOrderCompleteProcessor
{
    public function __construct(
        private PaymentStateManagerInterface $paymentStateManager,
        private ?PaymentAmountVerifierInterface $paymentAmountVerifier = null,
    ) {
        if (null === $this->paymentAmountVerifier) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing an instance of "%s" as the second argument is deprecated and will be prohibited in 3.0.',
                PaymentAmountVerifierInterface::class,
            );
        }
    }

    public function completePayPalOrder(OrderInterface $order): void
    {
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        if ($payment === null) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
            return;
        }

        try {
            if (null !== $this->paymentAmountVerifier) {
                $this->paymentAmountVerifier->verify($payment);
            } else {
                $this->verify($payment);
            }
        } catch (PaymentAmountMismatchException) {
            $this->paymentStateManager->cancel($payment);

            return;
        }

        $this->paymentStateManager->complete($payment);
    }

    private function verify(PaymentInterface $payment): void
    {
        $totalAmount = $this->getTotalPaymentAmountFromPaypal($payment);

        if ($payment->getOrder()->getTotal() !== $totalAmount) {
            throw new PaymentAmountMismatchException();
        }
    }

    private function getTotalPaymentAmountFromPaypal(PaymentInterface $payment): int
    {
        return PayPalPaymentDetails::fromPayment($payment)->amount() ?? 0;
    }
}
