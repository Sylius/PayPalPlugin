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

namespace Sylius\PayPalPlugin\Remover;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final readonly class PayPalPaymentDetailsRemover implements PayPalPaymentDetailsRemoverInterface
{
    public function removeFromOrder(OrderInterface $order): void
    {
        foreach ($order->getPayments() as $payment) {
            if ($this->hasStalePayPalDetails($payment)) {
                $payment->setDetails([]);
            }
        }
    }

    private function hasStalePayPalDetails(PaymentInterface $payment): bool
    {
        if (!in_array($payment->getState(), [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW], true)) {
            return false;
        }

        $paymentMethod = $payment->getMethod();
        if (
            $paymentMethod instanceof PaymentMethodInterface &&
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $paymentMethod->getGatewayConfig()?->getFactoryName()
        ) {
            return false;
        }

        return
            PayPalPaymentDetails::fromPayment($payment)->hasPayPalOrderId() ||
            array_key_exists(PayPalPaymentDetails::PAYMENT_SOURCE, $payment->getDetails())
        ;
    }
}
