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
    public function test_it_tells_which_webhook_was_pointed_at_which_url_and_whether_it_is_new(): void
    {
        $registration = new WebhookRegistration('WH-123', 'https://shop.example.com/payment-methods/PAYPAL', true);

        self::assertSame('WH-123', $registration->id());
        self::assertSame('https://shop.example.com/payment-methods/PAYPAL', $registration->url());
        self::assertTrue($registration->isNew());
        self::assertFalse((new WebhookRegistration('WH-123', 'https://shop.example.com/payment-methods/PAYPAL', false))->isNew());
    }
}
