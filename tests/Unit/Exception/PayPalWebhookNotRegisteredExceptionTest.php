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

namespace Tests\Sylius\PayPalPlugin\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;

final class PayPalWebhookNotRegisteredExceptionTest extends TestCase
{
    public function test_it_describes_the_refused_registration_without_the_console_hint_of_its_message(): void
    {
        $exception = new PayPalWebhookNotRegisteredException('PAYPAL', 'https://shop.example.com/payment-methods/PAYPAL', 'VALIDATION_ERROR');

        $description = 'PayPal has no webhook for the payment method "PAYPAL" and refused to register one at "https://shop.example.com/payment-methods/PAYPAL": VALIDATION_ERROR.';
        self::assertSame($description, $exception->getDescription());
        self::assertSame($description . ' Set the "sylius_paypal.webhook_base_url" parameter when the URL is generated outside a request.', $exception->getMessage());
    }
}
