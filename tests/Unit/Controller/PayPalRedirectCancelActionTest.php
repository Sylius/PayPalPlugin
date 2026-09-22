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
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Controller\PayPalRedirectCancelAction;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalRedirectCancelActionTest extends TestCase
{
    private OrderProviderInterface&MockObject $orderProvider;

    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private StateMachineInterface&MockObject $stateMachine;

    private OrderProcessorInterface&MockObject $orderPaymentProcessor;

    private ObjectManager&MockObject $objectManager;

    private FlashBag $flashBag;

    private OrderInterface&MockObject $order;

    private PayPalRedirectCancelAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderProvider = $this->createMock(OrderProviderInterface::class);
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->orderPaymentProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->objectManager = $this->createMock(ObjectManager::class);

        $this->order = $this->createMock(OrderInterface::class);
        $this->order->method('getTokenValue')->willReturn('ORDER_TOKEN');
        $this->orderProvider->method('provideOrderByToken')->willReturn($this->order);

        $this->flashBag = new FlashBag();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage(), null, $this->flashBag));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route): string => 'https://shop.example.com/' . $route,
        );

        $this->action = new PayPalRedirectCancelAction(
            $this->orderProvider,
            $this->paymentSettlementProcessor,
            $this->stateMachine,
            $this->orderPaymentProcessor,
            $this->objectManager,
            $router,
            $requestStack,
        );
    }

    public function test_it_cancels_the_attempt_the_payer_walked_away_from(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $this->order->method('getLastPayment')->willReturn($payment);
        $this->stateMachine->method('can')->willReturn(true);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)
        ;
        $this->orderPaymentProcessor->expects(self::once())->method('process')->with($this->order);
        $this->objectManager->expects(self::once())->method('flush');

        ($this->action)($this->request());

        self::assertSame(['sylius_paypal.payment_cancelled'], $this->flashBag->peek('info'));
    }

    public function test_it_asks_paypal_before_cancelling_and_keeps_a_payment_that_actually_went_through(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_COMPLETED);
        $this->order->method('getLastPayment')->willReturn($payment);

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($payment);
        $this->stateMachine->expects(self::never())->method('apply');

        $response = ($this->action)($this->request());

        self::assertSame('https://shop.example.com/sylius_shop_order_thank_you', $response->getTargetUrl());
    }

    public function test_it_sends_the_payer_back_to_the_payment_page_to_choose_again(): void
    {
        $cancelled = $this->createMock(PaymentInterface::class);
        $cancelled->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);

        $fresh = $this->createMock(PaymentInterface::class);
        $fresh->method('getId')->willReturn(42);

        $this->order
            ->method('getLastPayment')
            ->willReturnCallback(static fn (string $state): ?PaymentInterface => match ($state) {
                PaymentInterface::STATE_PROCESSING => $cancelled,
                PaymentInterface::STATE_NEW => $fresh,
                default => null,
            })
        ;
        $this->stateMachine->method('can')->willReturn(true);

        $response = ($this->action)($this->request());

        self::assertSame(
            'https://shop.example.com/sylius_paypal_shop_pay_with_paypal_form',
            $response->getTargetUrl(),
        );
    }

    private function request(): Request
    {
        $request = new Request();
        $request->attributes->set('token', 'ORDER_TOKEN');

        return $request;
    }
}
