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
        // Unlike provideOrderByToken()/findOneByTokenValue(), which deliberately excludes orders still in
        // the "cart" state (it's meant for looking up an already-placed order to pay/re-pay), the v6
        // shortcut and payment-page placements call this while the order is still being checked out - in
        // Sylius, an order stays in the "cart" state for the whole checkout process, only flipping to "new"
        // once it's actually placed. A plain, unfiltered lookup is what "the order I'm currently checking
        // out, identified by its token" actually means here.
        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);

        if ($order === null) {
            throw OrderNotFoundException::withToken($tokenValue);
        }

        return $order;
    }
}
