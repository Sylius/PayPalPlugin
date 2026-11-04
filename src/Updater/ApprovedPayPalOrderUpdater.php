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

namespace Sylius\PayPalPlugin\Updater;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final readonly class ApprovedPayPalOrderUpdater implements ApprovedPayPalOrderUpdaterInterface
{
    public function __construct(
        private UpdateOrderApiInterface $updateOrderApi,
        private UpdateOrderAddressApiInterface $updateOrderAddressApi,
        private PaymentUpdaterInterface $paymentUpdater,
        private StateResolverInterface $orderPaymentStateResolver,
    ) {
    }

    public function update(PaymentInterface $payment): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $details = PayPalPaymentDetails::fromPayment($payment);
        $payPalOrderId = (string) $details->payPalOrderId();

        if ($payment->getAmount() !== $order->getTotal()) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();

            $this->updateOrderApi->update(
                $paymentMethod,
                $payPalOrderId,
                $payment,
                (string) $details->referenceId(),
                PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->merchantId(),
            );

            $this->paymentUpdater->updateAmount($payment, $order->getTotal());
            $this->orderPaymentStateResolver->resolve($order);
        }

        $shippingAddress = $order->getShippingAddress();
        if ($order->isShippingRequired() && null !== $shippingAddress) {
            $this->updateOrderAddressApi->update(
                $paymentMethod,
                $payPalOrderId,
                (string) $details->referenceId(),
                $shippingAddress,
            );
        }
    }
}
