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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Exception\OrderNotFoundException;
use Sylius\PayPalPlugin\Provider\OrderProvider;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;

final class OrderProviderTest extends TestCase
{
    /** @var OrderRepositoryInterface<OrderInterface>&MockObject */
    private OrderRepositoryInterface&MockObject $orderRepository;

    private OrderProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);

        $this->provider = new OrderProvider($this->orderRepository);
    }

    #[Test]
    public function is_an_order_provider(): void
    {
        self::assertInstanceOf(OrderProviderInterface::class, $this->provider);
    }

    #[Test]
    public function provides_order_by_given_id(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('find')->with(420)->willReturn($order);

        $result = $this->provider->provideOrderById(420);

        self::assertSame($order, $result);
    }

    #[Test]
    public function provides_order_by_given_token(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('findOneByTokenValue')->with('token-str')->willReturn($order);

        $result = $this->provider->provideOrderByToken('token-str');

        self::assertSame($order, $result);
    }

    #[Test]
    public function throws_error_if_order_is_not_found_by_id(): void
    {
        $this->orderRepository->method('find')->with(123)->willReturn(null);

        $this->expectException(OrderNotFoundException::class);
        $this->provider->provideOrderById(123);
    }

    #[Test]
    public function throws_error_if_order_is_not_found_by_token(): void
    {
        $this->orderRepository->method('findOneByTokenValue')->with('token')->willReturn(null);

        $this->expectException(OrderNotFoundException::class);
        $this->provider->provideOrderByToken('token');
    }

    #[Test]
    public function provides_a_cart_by_its_token(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('findCartByTokenValue')->with('token-str')->willReturn($order);

        $result = $this->provider->provideCartByToken('token-str');

        self::assertSame($order, $result);
    }

    #[Test]
    public function throws_error_if_a_cart_is_not_found_by_token(): void
    {
        $this->orderRepository->method('findCartByTokenValue')->with('token')->willReturn(null);

        $this->expectException(OrderNotFoundException::class);
        $this->provider->provideCartByToken('token');
    }

    #[Test]
    public function provides_an_order_by_token_regardless_of_its_state(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('findOneBy')->with(['tokenValue' => 'token-str'])->willReturn($order);

        $result = $this->provider->provideOrderByTokenIncludingCart('token-str');

        self::assertSame($order, $result);
    }

    #[Test]
    public function throws_error_if_no_order_is_found_by_token_regardless_of_state(): void
    {
        $this->orderRepository->method('findOneBy')->with(['tokenValue' => 'token'])->willReturn(null);

        $this->expectException(OrderNotFoundException::class);
        $this->provider->provideOrderByTokenIncludingCart('token');
    }
}
