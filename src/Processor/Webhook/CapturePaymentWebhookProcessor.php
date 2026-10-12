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

use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;

final readonly class CapturePaymentWebhookProcessor implements WebhookProcessorInterface
{
    public const EVENT_TYPES = [
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.DENIED',
        'PAYMENT.CAPTURE.DECLINED',
        'PAYMENT.CAPTURE.PENDING',
    ];

    public function __construct(
        private SettleablePaypalPaymentQueryInterface $paypalPaymentQuery,
        private PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::EVENT_TYPES, true);
    }

    public function process(array $payload): void
    {
        $payPalOrderId = $this->payPalOrderId($payload);
        if (null === $payPalOrderId) {
            $this->logger->warning('A PayPal capture event carried no order id to settle a payment against.');

            return;
        }

        try {
            $payment = $this->paypalPaymentQuery->getForSettlementByOrderId($payPalOrderId);
        } catch (PaymentNotFoundException) {
            return;
        }

        $this->paymentSettlementProcessor->settle($payment);
    }

    /** @param array<string, mixed> $payload */
    private function payPalOrderId(array $payload): ?string
    {
        $payPalOrderId = $payload['resource']['supplementary_data']['related_ids']['order_id'] ?? null;

        return is_string($payPalOrderId) && '' !== $payPalOrderId ? $payPalOrderId : null;
    }
}
