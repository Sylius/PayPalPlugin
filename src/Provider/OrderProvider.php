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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Exception\OrderNotFoundException;

final readonly class OrderProvider implements OrderProviderInterface
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(private OrderRepositoryInterface $orderRepository)
    {
    }

    public function provideOrderById(int $id): OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->find($id);

        if ($order === null) {
            throw OrderNotFoundException::withId($id);
        }

        return $order;
    }

    public function provideOrderByToken(string $token): OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findOneByTokenValue($token);

        if ($order === null) {
            throw OrderNotFoundException::withToken($token);
        }

        return $order;
    }

    public function provideCartByToken(string $tokenValue): OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findCartByTokenValue($tokenValue);

        if ($order === null) {
            throw OrderNotFoundException::withToken($tokenValue);
        }

        return $order;
    }

    public function provideOrderByTokenIncludingCart(string $tokenValue): OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);

        if ($order === null) {
            throw OrderNotFoundException::withToken($tokenValue);
        }

        return $order;
    }
}
