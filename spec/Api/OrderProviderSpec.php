<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;

final class OrderProviderSpec extends ObjectBehavior
{
    function let(OrderRepositoryInterface $orderRepository): void
    {
        $this->beConstructedWith($orderRepository);
    }

    function it_is_an_order_provider(): void
    {
        $this->shouldImplement(OrderProviderInterface::class);
    }

    function it_provides_order_by_given_id(
        OrderRepositoryInterface $orderRepository,
        OrderInterface $order
    ): void {
        $orderRepository->find(2137)->willReturn($order);

        $this->provideOrderById(2137)->shouldReturn($order);
    }

    function it_provides_order_by_given_token(
        OrderRepositoryInterface $orderRepository,
        OrderInterface $order
    ): void {
        $orderRepository->findOneByTokenValue('token-str')->willReturn($order);

        $this->provideOrderByToken('token-str')->shouldReturn($order);
    }

    function it_throws_error_if_order_is_not_found(
        OrderRepositoryInterface $orderRepository
    ): void {
        $orderRepository->find(123)->willReturn(null);

        $this->shouldThrow(\TypeError::class)->during('provideOrderById', [123]);
    }
}
