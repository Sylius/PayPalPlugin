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
use Sylius\PayPalPlugin\Model\WebhookState;

final class WebhookStateTest extends TestCase
{
    public function test_it_tells_a_webhook_up_to_date_apart(): void
    {
        $state = WebhookState::upToDate('https://shop.example.com/payment-methods/PAYPAL');

        self::assertTrue($state->isUpToDate());
        self::assertTrue($state->isKnown());
        self::assertSame('https://shop.example.com/payment-methods/PAYPAL', $state->registeredUrl());
    }

    public function test_it_tells_a_webhook_of_unknown_state_apart(): void
    {
        $state = WebhookState::unknown();

        self::assertFalse($state->isUpToDate());
        self::assertFalse($state->isKnown());
        self::assertNull($state->registeredUrl());
    }

    public function test_it_tells_an_outdated_webhook_apart_with_what_is_wrong_with_it(): void
    {
        $state = WebhookState::outdated('https://shop.example.com/paypal-webhook/api/', true, ['PAYMENT.CAPTURE.DENIED']);

        self::assertFalse($state->isUpToDate());
        self::assertTrue($state->isKnown());
        self::assertTrue($state->pointsElsewhere());
        self::assertSame(['PAYMENT.CAPTURE.DENIED'], $state->missingEventTypes());
    }
}
