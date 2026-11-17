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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventNotification;

final class PayPalWebhookEventNotificationTest extends TestCase
{
    public function test_it_reads_a_paypal_webhook_event(): void
    {
        $notification = PayPalWebhookEventNotification::fromJson((string) json_encode([
            'id' => 'WH-1',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource_type' => 'capture',
            'resource' => ['id' => 'CAPTURE_ID', 'status' => 'COMPLETED'],
        ]));

        self::assertNotNull($notification);
        self::assertSame('WH-1', $notification->id());
        self::assertSame('PAYMENT.CAPTURE.COMPLETED', $notification->eventType());
        self::assertSame('capture', $notification->resourceType());
        self::assertSame('CAPTURE_ID', $notification->resourceId());
        self::assertSame(['id' => 'CAPTURE_ID', 'status' => 'COMPLETED'], $notification->resource());
    }

    #[DataProvider('unreadableEvents')]
    public function test_it_reads_nothing_from_a_notification_that_is_not_a_paypal_webhook_event(string $json): void
    {
        self::assertNull(PayPalWebhookEventNotification::fromJson($json));
    }

    /** @return iterable<string, array{string}> */
    public static function unreadableEvents(): iterable
    {
        $event = ['id' => 'WH-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource_type' => 'capture', 'resource' => ['id' => 'CAPTURE_ID']];

        yield 'not json' => ['not json'];
        yield 'no event id' => [(string) json_encode(array_diff_key($event, ['id' => true]))];
        yield 'no event type' => [(string) json_encode(array_diff_key($event, ['event_type' => true]))];
        yield 'no resource type' => [(string) json_encode(array_diff_key($event, ['resource_type' => true]))];
        yield 'no resource' => [(string) json_encode(array_diff_key($event, ['resource' => true]))];
        yield 'no resource id' => [(string) json_encode(['resource' => []] + $event)];
    }
}
