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
    public function it_is_a_http_exception_mapped_to_404(): void
    {
        $exception = OrderNotFoundException::withToken('FOREIGN_TOKEN');

        self::assertInstanceOf(HttpExceptionInterface::class, $exception);
        self::assertSame(404, $exception->getStatusCode());
        self::assertSame([], $exception->getHeaders());
    }

    #[Test]
    public function it_is_a_http_exception_mapped_to_404_when_built_with_id(): void
    {
        $exception = OrderNotFoundException::withId(123);

        self::assertSame(404, $exception->getStatusCode());
    }
}
