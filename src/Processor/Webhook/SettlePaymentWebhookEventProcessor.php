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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderDetailsFactoryInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;

final readonly class SettlePaymentWebhookEventProcessor implements WebhookEventProcessorInterface
{
    public const CAPTURE_EVENT_TYPES = [
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.DENIED',
        'PAYMENT.CAPTURE.DECLINED',
        'PAYMENT.CAPTURE.PENDING',
    ];

    public const AUTHORIZATION_EVENT_TYPES = [
        'PAYMENT.AUTHORIZATION.CREATED',
        'PAYMENT.AUTHORIZATION.VOIDED',
    ];

    public const ORDER_EVENT_TYPE = 'CHECKOUT.ORDER.COMPLETED';

    public function __construct(
        private PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        private PayPalOrderDetailsFactoryInterface $payPalOrderDetailsFactory,
    ) {
    }

    public function process(string $eventType, array $resource, PaymentInterface $payment): void
    {
        if (self::ORDER_EVENT_TYPE === $eventType) {
            $payPalOrderDetails = $this->payPalOrderDetailsFactory->create($resource);
            $this->paymentSettlementProcessor->settle($payment, $payPalOrderDetails, $payPalOrderDetails->id());

            return;
        }

        $payPalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        $this->paymentSettlementProcessor->settle(
            $payment,
            payPalOrderId: is_string($payPalOrderId) && '' !== $payPalOrderId ? $payPalOrderId : null,
        );
    }
}
