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
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final readonly class PayPalPaymentRefundProcessor implements PaymentRefundProcessorInterface
{
    private const CAPTURE_STATUS_REFUNDED = 'REFUNDED';

    private const CAPTURE_FULLY_REFUNDED = 'CAPTURE_FULLY_REFUNDED';

    public function __construct(
        private OrderDetailsApiInterface $orderDetailsApi,
        private RefundPaymentApiInterface $refundOrderApi,
        private PayPalAuthAssertionGeneratorInterface $payPalAuthAssertionGenerator,
        private RefundReferenceNumberProviderInterface $refundReferenceNumberProvider,
    ) {
    }

    public function refund(PaymentInterface $payment): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
            return;
        }

        $payPalOrderId = PayPalPaymentDetails::fromPayment($payment)->payPalOrderId();
        if (null === $payPalOrderId) {
            return;
        }

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        try {
            $details = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
            $capture = $details['purchase_units'][0]['payments']['captures'][0];
            if (self::CAPTURE_STATUS_REFUNDED === ($capture['status'] ?? null)) {
                return;
            }

            $authAssertion = $this->payPalAuthAssertionGenerator->generate($paymentMethod);
            $referenceNumber = $this->refundReferenceNumberProvider->provide($payment);
            $payPalPaymentId = (string) $capture['id'];

            $this->refundOrderApi->refund(
                $paymentMethod,
                $payPalPaymentId,
                $authAssertion,
                $referenceNumber,
                (string) (((int) $payment->getAmount()) / 100),
                (string) $order->getCurrencyCode(),
            );
        } catch (PayPalApiException $exception) {
            if ($exception->hasIssue(self::CAPTURE_FULLY_REFUNDED)) {
                return;
            }

            throw new PayPalOrderRefundException();
        } catch (\InvalidArgumentException) {
            throw new PayPalOrderRefundException();
        }
    }
}
