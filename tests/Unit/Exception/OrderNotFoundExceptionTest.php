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
use Sylius\PayPalPlugin\Exception\OrderNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class OrderNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_answers_404(): void
    {
        $exception = OrderNotFoundException::withId(42);

        self::assertInstanceOf(HttpExceptionInterface::class, $exception);
        self::assertSame(404, $exception->getStatusCode());
        self::assertSame([], $exception->getHeaders());
    }

    #[Test]
    public function it_names_the_id_in_the_message(): void
    {
        self::assertSame('Order with id 42 not found', OrderNotFoundException::withId(42)->getMessage());
    }

    #[Test]
    public function it_names_the_token_in_the_message(): void
    {
        self::assertSame('Order with token "TOKEN" not found', OrderNotFoundException::withToken('TOKEN')->getMessage());
    }
}
