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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Exception\PayPalApiErrorException;

final class PayPalApiErrorExceptionTest extends TestCase
{
    #[Test]
    public function it_names_the_request_and_the_paypal_error_in_the_message(): void
    {
        $exception = new PayPalApiErrorException('GET v2/checkout/orders/ORDER123', [
            'name' => 'RESOURCE_NOT_FOUND',
            'message' => 'The specified resource does not exist.',
            'debug_id' => '9afb818786905',
        ]);

        self::assertSame(
            'PayPal rejected the "GET v2/checkout/orders/ORDER123" request: RESOURCE_NOT_FOUND The specified resource does not exist. (debug id: 9afb818786905)',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function it_describes_the_paypal_error_without_the_request(): void
    {
        $exception = new PayPalApiErrorException('GET v2/checkout/orders/ORDER123', [
            'name' => 'RESOURCE_NOT_FOUND',
            'message' => 'The specified resource does not exist.',
            'debug_id' => '9afb818786905',
        ]);

        self::assertSame('RESOURCE_NOT_FOUND The specified resource does not exist. (debug id: 9afb818786905)', $exception->getDescription());
    }

    #[Test]
    public function it_describes_an_oauth_error(): void
    {
        $exception = new PayPalApiErrorException('POST v2/checkout/orders/ORDER123/track', [
            'error' => 'invalid_token',
            'error_description' => 'Token signature verification failed',
        ]);

        self::assertSame('invalid_token Token signature verification failed', $exception->getDescription());
    }

    #[Test]
    public function it_describes_an_unreadable_response(): void
    {
        self::assertSame('the response could not be read', (new PayPalApiErrorException('POST v2/checkout/orders/ORDER123/track', []))->getDescription());
    }
}
