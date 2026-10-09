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
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final readonly class PaymentCaptureProcessor implements PaymentCaptureProcessorInterface
{
    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private UpdateOrderApiInterface $updateOrderApi,
        private UpdateOrderAddressApiInterface $updateOrderAddressApi,
        private CompleteOrderApiInterface $completeOrderApi,
        private OrderDetailsApiInterface $orderDetailsApi,
        private PaymentUpdaterInterface $paymentUpdater,
        private StateResolverInterface $orderPaymentStateResolver,
    ) {
    }

    public function capture(PaymentInterface $payment): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $details = PayPalPaymentDetails::fromPayment($payment);
        $payPalOrderId = (string) $details->payPalOrderId();

        $token = $this->authorizeClientApi->authorize($paymentMethod);

        if ($payment->getAmount() !== $order->getTotal()) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();

            $this->updateOrderApi->update(
                $token,
                $payPalOrderId,
                $payment,
                (string) $details->referenceId(),
                PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->merchantId(),
            );

            $this->paymentUpdater->updateAmount($payment, $order->getTotal());
            $this->orderPaymentStateResolver->resolve($order);
        }

        if ($order->isShippingRequired()) {
            $this->updateOrderAddressApi->update(
                $token,
                $payPalOrderId,
                (string) $details->referenceId(),
                $order->getShippingAddress(),
            );
        }

        $this->completeOrderApi->complete($token, $payPalOrderId);
        $orderDetails = $this->orderDetailsApi->get($token, $payPalOrderId);

        if (null === PayPalCapture::fromPayPalOrder($orderDetails)) {
            return $orderDetails;
        }

        $capturedDetails = PayPalPaymentDetails::create()
            ->withStatus('COMPLETED' === $orderDetails['status'] ? PayPalPaymentStatus::Completed : PayPalPaymentStatus::Processing)
            ->withPayPalOrderId((string) $orderDetails['id'])
            ->withReferenceId((string) $orderDetails['purchase_units'][0]['reference_id'])
            ->withPaymentSource($details->paymentSource())
        ;
        if (isset($orderDetails['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $capturedDetails = $capturedDetails->withTransactionId(
                (string) $orderDetails['purchase_units'][0]['payments']['captures'][0]['id'],
            );
        }

        $payment->setDetails($capturedDetails->toArray());

        return $orderDetails;
    }
}
