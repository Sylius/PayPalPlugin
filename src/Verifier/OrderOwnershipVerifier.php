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

namespace Sylius\PayPalPlugin\Verifier;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class OrderOwnershipVerifier implements OrderOwnershipVerifierInterface
{
    public function __construct(private CartContextInterface $cartContext)
    {
    }

    public function verify(OrderInterface $order, Request $request): void
    {
        if ($this->ownsCurrentCart($order) || $this->ownsJustCompletedOrder($order, $request)) {
            return;
        }

        throw new NotFoundHttpException();
    }

    private function ownsCurrentCart(OrderInterface $order): bool
    {
        try {
            return $this->cartContext->getCart()->getId() === $order->getId();
        } catch (CartNotFoundException) {
            return false;
        }
    }

    private function ownsJustCompletedOrder(OrderInterface $order, Request $request): bool
    {
        return $request->getSession()->get('sylius_order_id') === $order->getId();
    }
}
