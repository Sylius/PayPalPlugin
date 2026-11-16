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
use Sylius\PayPalPlugin\Model\WebhookRegistration;

final class WebhookRegistrationTest extends TestCase
{
    public function test_it_tells_which_webhook_was_pointed_at_which_url(): void
    {
        $registration = WebhookRegistration::updated('WH-123', 'https://shop.example.com/payment-methods/PAYPAL');

        self::assertSame('WH-123', $registration->id());
        self::assertSame('https://shop.example.com/payment-methods/PAYPAL', $registration->url());
    }

    public function test_it_tells_a_registered_webhook_apart(): void
    {
        $registration = WebhookRegistration::registered('WH-123', 'https://shop.example.com/payment-methods/PAYPAL');

        self::assertTrue($registration->isNew());
        self::assertFalse($registration->isUpToDate());
    }

    public function test_it_tells_an_updated_webhook_apart(): void
    {
        $registration = WebhookRegistration::updated('WH-123', 'https://shop.example.com/payment-methods/PAYPAL');

        self::assertFalse($registration->isNew());
        self::assertFalse($registration->isUpToDate());
    }

    public function test_it_tells_a_webhook_already_up_to_date_apart(): void
    {
        $registration = WebhookRegistration::upToDate('WH-123', 'https://shop.example.com/payment-methods/PAYPAL');

        self::assertFalse($registration->isNew());
        self::assertTrue($registration->isUpToDate());
    }
}
