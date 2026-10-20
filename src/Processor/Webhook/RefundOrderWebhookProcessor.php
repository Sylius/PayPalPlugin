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

namespace Sylius\PayPalPlugin\Processor\Webhook;

use Doctrine\Persistence\ObjectManager;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Processor\PayPalLateCaptureRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;

final readonly class RefundOrderWebhookProcessor implements WebhookProcessorInterface
{
    public const EVENT_TYPE = 'PAYMENT.CAPTURE.REFUNDED';

    public function __construct(
        private PayPalRefundDataProviderInterface $payPalRefundDataProvider,
        private PaypalPaymentQueryInterface $paypalPaymentQuery,
        private PayPalPaymentRefundConfirmationProcessorInterface $refundConfirmationProcessor,
        private ObjectManager $paymentManager,
        private SettleablePaypalPaymentQueryInterface $settleablePaymentQuery,
        private PayPalLateCaptureRefundConfirmationProcessorInterface $lateCaptureRefundConfirmationProcessor,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return self::EVENT_TYPE === $eventType;
    }

    public function process(array $payload): void
    {
        $captureUrl = $this->payPalOrderUrl($payload);
        $refundData = $this->payPalRefundDataProvider->provide($captureUrl);

        if ($this->markLateCaptureRefunded((string) $refundData['id'], $this->captureId($captureUrl), $payload)) {
            return;
        }

        try {
            $payment = $this->paypalPaymentQuery->getForRefundingByOrderId((string) $refundData['id']);
        } catch (PaymentNotFoundException) {
            return;
        }

        $refundId = $payload['resource']['id'] ?? null;
        $this->refundConfirmationProcessor->confirm($payment, is_string($refundId) ? $refundId : null);

        $this->paymentManager->flush();
    }

    /** @param array<string, mixed> $payload */
    private function markLateCaptureRefunded(string $payPalOrderId, string $captureId, array $payload): bool
    {
        try {
            $payment = $this->settleablePaymentQuery->getForSettlementByOrderId($payPalOrderId);
        } catch (PaymentNotFoundException) {
            return false;
        }

        $details = $payment->getDetails();
        $lateCapture = $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] ?? null;
        if (!is_array($lateCapture) || $captureId !== ($lateCapture['id'] ?? null)) {
            return false;
        }

        $refundId = $payload['resource']['id'] ?? null;
        $this->lateCaptureRefundConfirmationProcessor->confirm($payment, is_string($refundId) ? $refundId : null);

        $this->paymentManager->flush();

        return true;
    }

    private function captureId(string $captureUrl): string
    {
        return basename((string) parse_url($captureUrl, \PHP_URL_PATH));
    }

    /** @param array<string, mixed> $payload */
    private function payPalOrderUrl(array $payload): string
    {
        /** @var array<array{rel?: string, href?: string}> $links */
        $links = $payload['resource']['links'] ?? [];

        foreach ($links as $link) {
            if ('up' === ($link['rel'] ?? null) && isset($link['href'])) {
                return (string) $link['href'];
            }
        }

        throw new PayPalWrongDataException();
    }
}
