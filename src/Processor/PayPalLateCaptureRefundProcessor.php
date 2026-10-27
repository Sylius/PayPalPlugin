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

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\ClientException;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final readonly class PayPalLateCaptureRefundProcessor implements PaymentRefundProcessorInterface
{
    private const FAILED_REFUND_STATUSES = ['FAILED', 'CANCELLED'];

    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private OrderDetailsApiInterface $orderDetailsApi,
        private RefundPaymentApiInterface $refundPaymentApi,
        private PayPalAuthAssertionGeneratorInterface $payPalAuthAssertionGenerator,
        private RefundReferenceNumberProviderInterface $refundReferenceNumberProvider,
        private ObjectManager $paymentManager,
    ) {
    }

    public function refund(PaymentInterface $payment): void
    {
        $details = $payment->getDetails();
        $lateCapture = $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] ?? null;
        $payPalOrderId = $details['paypal_order_id'] ?? null;

        if (
            !$this->isPayPalPayment($payment) ||
            !is_string($payPalOrderId) ||
            !is_array($lateCapture) ||
            !is_string($lateCapture['id'] ?? null) ||
            !is_int($lateCapture['amount'] ?? null) ||
            !is_string($lateCapture['currency_code'] ?? null) ||
            true === ($lateCapture['refunded'] ?? false)
        ) {
            throw new PayPalOrderRefundException();
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        try {
            $token = $this->authorizeClientApi->authorize($paymentMethod);

            $capture = PayPalCapture::fromPayPalOrder($this->orderDetailsApi->get($token, $payPalOrderId));
            if (PayPalCapture::STATUS_REFUNDED === $capture?->status()) {
                $this->markRefunded($payment, $details, $lateCapture, null);

                return;
            }

            $response = $this->refundPaymentApi->refund(
                $token,
                $lateCapture['id'],
                $this->payPalAuthAssertionGenerator->generate($paymentMethod),
                $this->refundReferenceNumberProvider->provide($payment),
                AmountUtils::toPayPalValue($lateCapture['amount'], $lateCapture['currency_code']),
                $lateCapture['currency_code'],
            );
        } catch (ClientException|PayPalAuthorizationException) {
            throw new PayPalOrderRefundException();
        }

        if (!is_string($response['id'] ?? null) || in_array($response['status'] ?? null, self::FAILED_REFUND_STATUSES, true)) {
            throw new PayPalOrderRefundException();
        }

        $this->markRefunded($payment, $details, $lateCapture, $response['id']);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, mixed> $lateCapture
     */
    private function markRefunded(PaymentInterface $payment, array $details, array $lateCapture, ?string $refundId): void
    {
        $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] = array_merge($lateCapture, [
            'refunded' => true,
            'refund_id' => $refundId,
        ]);
        $payment->setDetails($details);
        $this->paymentManager->flush();
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
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $gatewayConfig->getFactoryName()
        ;
    }
}
