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

namespace Tests\Sylius\PayPalPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Entity\PayPalWebhookEvent;
use Sylius\PayPalPlugin\Entity\PayPalWebhookEventInterface;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;

final class PayPalWebhookEventTest extends TestCase
{
    public function test_it_is_a_received_event_until_told_otherwise(): void
    {
        $event = new PayPalWebhookEvent('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED');

        self::assertInstanceOf(PayPalWebhookEventInterface::class, $event);
        self::assertSame('WH-1', $event->getEventId());
        self::assertSame('PAYPAL', $event->getPaymentMethodCode());
        self::assertSame('PAYMENT.CAPTURE.COMPLETED', $event->getEventType());
        self::assertSame(PayPalWebhookEventStatus::Received, $event->getStatus());
        self::assertNull($event->getUpdatedAt());
    }

    public function test_it_records_when_it_was_received(): void
    {
        $before = new \DateTime();

        $event = new PayPalWebhookEvent('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED', PayPalWebhookEventStatus::Processed);

        self::assertGreaterThanOrEqual($before, $event->getCreatedAt());
        self::assertSame(PayPalWebhookEventStatus::Processed, $event->getStatus());
    }
}
