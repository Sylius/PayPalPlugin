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
use Sylius\PayPalPlugin\Controller\CompletePayPalOrderFromPaymentPageAction;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderFromPaymentPageActionTest extends TestCase
{
    private PaymentStateManagerInterface&MockObject $paymentStateManager;

    private OrderProviderInterface&Stub $orderProvider;

    private OrderOwnershipVerifierInterface&Stub $orderOwnershipVerifier;

    private StateMachineInterface&MockObject $stateMachine;

    private ObjectManager&MockObject $orderManager;

    private PaymentAmountVerifierInterface&MockObject $paymentAmountVerifier;

    private OrderProcessorInterface&MockObject $orderProcessor;

    private FlashBagInterface&MockObject $flashBag;

    private OrderInterface&MockObject $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentStateManager = $this->createMock(PaymentStateManagerInterface::class);
        $this->orderProvider = $this->createStub(OrderProviderInterface::class);
        $this->orderOwnershipVerifier = $this->createStub(OrderOwnershipVerifierInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->orderManager = $this->createMock(ObjectManager::class);
        $this->paymentAmountVerifier = $this->createMock(PaymentAmountVerifierInterface::class);
        $this->orderProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);

        $this->order = $this->createMock(OrderInterface::class);
        $this->order->method('getId')->willReturn(42);
        $this->orderProvider->method('provideOrderById')->with(42)->willReturn($this->order);
    }

    public function test_it_completes_the_payment_when_the_amount_matches(): void
    {
        $payment = $this->processingPayment();

        $this->paymentStateManager->expects(self::once())->method('complete')->with($payment);
        $this->paymentStateManager->expects(self::never())->method('cancel');
        $this->orderManager->expects(self::once())->method('flush');

        $content = $this->invoke();

        self::assertSame('THANK_YOU_URL', $content['return_url']);
    }

    public function test_it_cancels_the_payment_and_reprocesses_the_order_when_the_amount_does_not_match(): void
    {
        $payment = $this->processingPayment();
        $this->amountDoesNotMatch();

        $this->paymentStateManager->expects(self::once())->method('cancel')->with($payment);
        $this->orderProcessor->expects(self::once())->method('process')->with($this->order);

        $this->invoke();
    }

    public function test_it_persists_the_order_when_the_amount_does_not_match(): void
    {
        $this->processingPayment();
        $this->amountDoesNotMatch();

        $this->orderManager->expects(self::once())->method('flush');

        $this->invoke();
    }

    public function test_it_keeps_the_cancelled_payment_on_the_order_when_the_amount_does_not_match(): void
    {
        $this->processingPayment();
        $this->amountDoesNotMatch();

        $this->order->expects(self::never())->method('removePayment');

        $this->invoke();
    }

    public function test_it_does_not_complete_the_order_when_the_amount_does_not_match(): void
    {
        $this->processingPayment();
        $this->amountDoesNotMatch();

        $this->stateMachine->expects(self::never())->method('apply');
        $this->paymentStateManager->expects(self::never())->method('complete');

        $this->invoke();
    }

    public function test_it_sends_the_buyer_back_to_the_summary_when_the_amount_does_not_match(): void
    {
        $this->processingPayment();
        $this->amountDoesNotMatch();

        $content = $this->invoke();

        self::assertSame('CHECKOUT_COMPLETE_URL', $content['return_url']);
        self::assertSame('PAYPAL_ORDER_ID', $content['orderId']);
    }

    public function test_it_tells_the_buyer_why_the_payment_was_not_taken(): void
    {
        $this->processingPayment();
        $this->amountDoesNotMatch();

        $this->flashBag->expects(self::once())->method('add')->with('error', 'sylius_paypal.order_total_changed');

        $this->invoke();
    }

    public function test_it_refuses_to_run_without_an_order_ownership_verifier(): void
    {
        $action = new CompletePayPalOrderFromPaymentPageAction(
            $this->paymentStateManager,
            $this->router(),
            $this->orderProvider,
            $this->stateMachine,
            $this->orderManager,
        );

        self::expectException(\RuntimeException::class);

        $action($this->request());
    }

    public function test_it_answers_with_a_conflict_when_no_payment_is_being_processed(): void
    {
        $this->paymentAmountVerifier->expects(self::never())->method('verify');
        $this->paymentStateManager->expects(self::never())->method('complete');
        $this->paymentStateManager->expects(self::never())->method('cancel');
        $this->stateMachine->expects(self::never())->method('apply');
        $this->orderManager->expects(self::never())->method('flush');

        self::assertSame(Response::HTTP_CONFLICT, ($this->action())($this->request())->getStatusCode());
    }

    /** @return array<string, mixed> */
    private function invoke(): array
    {
        return (array) json_decode((string) ($this->action())($this->request())->getContent(), true);
    }

    private function action(): CompletePayPalOrderFromPaymentPageAction
    {
        return new CompletePayPalOrderFromPaymentPageAction(
            $this->paymentStateManager,
            $this->router(),
            $this->orderProvider,
            $this->stateMachine,
            $this->orderManager,
            $this->paymentAmountVerifier,
            $this->orderProcessor,
            $this->orderOwnershipVerifier,
        );
    }

    private function amountDoesNotMatch(): void
    {
        $this->paymentAmountVerifier->method('verify')->willThrowException(new PaymentAmountMismatchException());
    }

    private function processingPayment(string $state = PaymentInterface::STATE_PROCESSING): PaymentInterface&Stub
    {
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getId')->willReturn(1);
        $payment->method('getState')->willReturn($state);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        $this->order->method('getLastPayment')->willReturnCallback(
            static fn (?string $requested = null): ?PaymentInterface => PaymentInterface::STATE_PROCESSING === $requested ? $payment : null,
        );

        return $payment;
    }

    private function router(): UrlGeneratorInterface&Stub
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'sylius_shop_order_thank_you' => 'THANK_YOU_URL',
            'sylius_shop_checkout_complete' => 'CHECKOUT_COMPLETE_URL',
            default => 'UNKNOWN_URL',
        });

        return $router;
    }

    private function request(): Request
    {
        $session = $this->createStub(SessionInterface::class);
        $session->method('getBag')->with('flashes')->willReturn($this->flashBag);

        $request = new Request(attributes: ['id' => 42]);
        $request->setSession($session);

        return $request;
    }
}
