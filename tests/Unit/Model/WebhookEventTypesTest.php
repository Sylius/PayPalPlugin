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

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\WebhookEventTypes;

final class WebhookEventTypesTest extends TestCase
{
    public function test_it_misses_nothing_when_it_has_every_event_the_plugin_handles(): void
    {
        self::assertSame(WebhookEventTypes::HANDLED, WebhookEventTypes::handled()->names());
        self::assertSame([], WebhookEventTypes::handled()->missing());
        self::assertSame([], WebhookEventTypes::fromNames([...WebhookEventTypes::HANDLED, 'VAULT.PAYMENT-TOKEN.CREATED'])->missing());
    }

    public function test_it_lists_the_events_the_plugin_handles_that_it_misses(): void
    {
        self::assertSame(
            ['PAYMENT.AUTHORIZATION.CREATED', 'PAYMENT.AUTHORIZATION.VOIDED'],
            WebhookEventTypes::fromNames(array_slice(WebhookEventTypes::HANDLED, 0, -2))->missing(),
        );
    }

    public function test_it_reads_the_event_types_of_a_webhook_paypal_returns(): void
    {
        $eventTypes = WebhookEventTypes::fromPayPal([
            ['name' => 'PAYMENT.CAPTURE.COMPLETED', 'description' => 'A payment capture completes.'],
            ['description' => 'An event without a name.'],
            'PAYMENT.CAPTURE.DENIED',
        ]);

        self::assertSame(['PAYMENT.CAPTURE.COMPLETED'], $eventTypes->names());
        self::assertSame([], WebhookEventTypes::fromPayPal(null)->names());
    }

    public function test_it_gives_the_event_types_in_the_shape_paypal_expects(): void
    {
        self::assertSame(
            [['name' => 'PAYMENT.CAPTURE.COMPLETED'], ['name' => 'PAYMENT.CAPTURE.REFUNDED']],
            WebhookEventTypes::fromNames(['PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.REFUNDED'])->toPayPal(),
        );
    }
}
