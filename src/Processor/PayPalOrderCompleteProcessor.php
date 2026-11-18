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

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;

/** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
final readonly class PayPalOrderCompleteProcessor
{
    public function __construct(
        private PaymentStateManagerInterface $paymentStateManager,
        private ?PaymentAmountVerifierInterface $paymentAmountVerifier = null,
    ) {
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
        $details = $payment->getDetails();

        return $details['payment_amount'] ?? 0;
    }
}
