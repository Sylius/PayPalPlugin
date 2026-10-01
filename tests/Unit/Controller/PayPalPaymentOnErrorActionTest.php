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
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Controller\PayPalPaymentOnErrorAction;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class PayPalPaymentOnErrorActionTest extends TestCase
{
    private RequestStack $requestStack;

    private LoggerInterface&MockObject $logger;

    private PaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private StateMachineInterface&MockObject $stateMachine;

    private OrderProcessorInterface&MockObject $orderPaymentProcessor;

    private ObjectManager&MockObject $objectManager;

    private PayPalPaymentOnErrorAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestStack = new RequestStack();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->paypalPaymentQuery = $this->createMock(PaypalPaymentQueryInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->orderPaymentProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->objectManager = $this->createMock(ObjectManager::class);

        $this->action = new PayPalPaymentOnErrorAction(
            $this->requestStack,
            $this->logger,
            $this->paypalPaymentQuery,
            $this->stateMachine,
            $this->orderPaymentProcessor,
            $this->objectManager,
        );
    }

    public function test_it_logs_the_error_and_flashes_a_message(): void
    {
        $this->logger->expects(self::once())->method('error')->with('AbortError: the window timed out');
        $this->paypalPaymentQuery->expects(self::never())->method('getForCancellationByOrderId');

        $request = $this->request('AbortError: the window timed out');

        self::assertSame(Response::HTTP_OK, ($this->action)($request)->getStatusCode());
        self::assertSame(['sylius_paypal.something_went_wrong'], $request->getSession()->getBag('flashes')->get('error'));
    }

    public function test_it_logs_the_error_message_from_a_json_payload(): void
    {
        $this->logger->expects(self::once())->method('error')->with('AbortError: the window timed out');

        ($this->action)($this->request((string) json_encode(['error' => 'AbortError: the window timed out'])));
    }

    public function test_it_cancels_the_payment_named_by_the_payload(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $this->paypalPaymentQuery->method('getForCancellationByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);
        $this->stateMachine->method('can')->willReturn(true);

        $this->stateMachine->expects(self::once())->method('apply')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
        $this->orderPaymentProcessor->expects(self::once())->method('process')->with($order);
        $this->objectManager->expects(self::once())->method('flush');

        ($this->action)($this->request($this->payload()));
    }

    public function test_it_leaves_a_payment_that_cannot_be_cancelled_alone(): void
    {
        $this->paypalPaymentQuery->method('getForCancellationByOrderId')->willReturn($this->createStub(PaymentInterface::class));
        $this->stateMachine->method('can')->willReturn(false);

        $this->stateMachine->expects(self::never())->method('apply');
        $this->orderPaymentProcessor->expects(self::never())->method('process');
        $this->objectManager->expects(self::never())->method('flush');

        ($this->action)($this->request($this->payload()));
    }

    public function test_it_ignores_an_unknown_paypal_order_id(): void
    {
        $this->paypalPaymentQuery->method('getForCancellationByOrderId')->willThrowException(new PaymentNotFoundException());

        $this->objectManager->expects(self::never())->method('flush');

        self::assertSame(Response::HTTP_OK, ($this->action)($this->request($this->payload()))->getStatusCode());
    }

    public function test_it_only_logs_when_the_cancelling_collaborators_are_missing(): void
    {
        $action = new PayPalPaymentOnErrorAction($this->requestStack, $this->logger);

        $this->logger->expects(self::once())->method('error')->with('Something went wrong');
        $this->objectManager->expects(self::never())->method('flush');

        self::assertSame(Response::HTTP_OK, $action($this->request($this->payload()))->getStatusCode());
    }

    private function payload(): string
    {
        return (string) json_encode(['error' => 'Something went wrong', 'payPalOrderId' => 'PAYPAL_ORDER_ID']);
    }

    private function request(string $content): Request
    {
        $request = new Request([], [], [], [], [], [], $content);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);

        return $request;
    }
}
