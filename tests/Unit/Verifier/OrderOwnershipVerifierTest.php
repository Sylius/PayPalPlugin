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

namespace Tests\Sylius\PayPalPlugin\Unit\Verifier;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifier;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OrderOwnershipVerifierTest extends TestCase
{
    private CartContextInterface&Stub $cartContext;

    private OrderOwnershipVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartContext = $this->createStub(CartContextInterface::class);
        $this->verifier = new OrderOwnershipVerifier($this->cartContext);
    }

    public function test_it_implements_order_ownership_verifier_interface(): void
    {
        self::assertInstanceOf(OrderOwnershipVerifierInterface::class, $this->verifier);
    }

    public function test_it_accepts_an_order_that_is_the_current_cart(): void
    {
        $order = $this->order(42);
        $this->cartContext->method('getCart')->willReturn($order);

        $this->verifier->verify($order, $this->request());

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_an_order_just_completed_in_this_session(): void
    {
        $order = $this->order(42);
        $this->cartContext->method('getCart')->willThrowException(new CartNotFoundException());

        $this->verifier->verify($order, $this->request(completedOrderId: 42));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_rejects_an_order_that_belongs_to_neither_the_current_cart_nor_a_just_completed_order(): void
    {
        $order = $this->order(42);
        $this->cartContext->method('getCart')->willReturn($this->order(99));

        $this->expectException(NotFoundHttpException::class);

        $this->verifier->verify($order, $this->request(completedOrderId: 99));
    }

    public function test_it_rejects_an_order_when_there_is_no_cart_and_no_completed_order_in_the_session(): void
    {
        $order = $this->order(42);
        $this->cartContext->method('getCart')->willThrowException(new CartNotFoundException());

        $this->expectException(NotFoundHttpException::class);

        $this->verifier->verify($order, $this->request());
    }

    private function order(int $id): OrderInterface&Stub
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getId')->willReturn($id);

        return $order;
    }

    private function request(?int $completedOrderId = null): Request
    {
        $session = new Session(new MockArraySessionStorage());
        if (null !== $completedOrderId) {
            $session->set('sylius_order_id', $completedOrderId);
        }

        $request = new Request();
        $request->setSession($session);

        return $request;
    }
}
