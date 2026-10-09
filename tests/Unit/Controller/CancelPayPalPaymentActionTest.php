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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Controller\CancelPayPalPaymentAction;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CancelPayPalPaymentActionTest extends TestCase
{
    private ObjectManager&MockObject $objectManager;

    private StateMachineInterface&MockObject $stateMachine;

    private OrderProcessorInterface&MockObject $orderPaymentProcessor;

    private PaypalPaymentQueryInterface&Stub $paypalPaymentQuery;

    private Session $session;

    private CancelPayPalPaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = $this->createMock(ObjectManager::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->orderPaymentProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->paypalPaymentQuery = $this->createStub(PaypalPaymentQueryInterface::class);
        $this->session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->action = new CancelPayPalPaymentAction(
            null,
            $this->objectManager,
            $requestStack,
            $this->stateMachine,
            $this->orderPaymentProcessor,
            $this->paypalPaymentQuery,
        );
    }

    public function test_it_cancels_the_payment_and_gives_a_cart_a_new_payment(): void
    {
        $order = $this->order(canBeProcessed: true);

        $this->orderPaymentProcessor->expects(self::once())->method('process')->with($order);
        $this->objectManager->expects(self::once())->method('flush');

        $response = ($this->action)($this->request());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame(['sylius_paypal.payment_cancelled'], $this->session->getFlashBag()->peek('success'));
    }

    public function test_it_leaves_the_new_payment_of_a_placed_order_to_the_cancel_transition(): void
    {
        $this->order(canBeProcessed: false);

        $this->orderPaymentProcessor->expects(self::never())->method('process');
        $this->objectManager->expects(self::once())->method('flush');

        ($this->action)($this->request());
    }

    public function test_it_leaves_a_payment_that_cannot_be_cancelled_alone(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($this->createStub(OrderInterface::class));
        $this->paypalPaymentQuery->method('getForCancellationByOrderId')->willReturn($payment);
        $this->stateMachine->method('can')->willReturn(false);

        $this->stateMachine->expects(self::never())->method('apply');
        $this->orderPaymentProcessor->expects(self::never())->method('process');
        $this->objectManager->expects(self::never())->method('flush');

        ($this->action)($this->request());
    }

    private function order(bool $canBeProcessed): OrderInterface
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('canBeProcessed')->willReturn($canBeProcessed);
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $this->paypalPaymentQuery->method('getForCancellationByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);
        $this->stateMachine->method('can')->willReturn(true);
        $this->stateMachine->expects(self::once())->method('apply')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

        return $order;
    }

    private function request(): Request
    {
        return new Request(content: (string) json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID']));
    }
}
