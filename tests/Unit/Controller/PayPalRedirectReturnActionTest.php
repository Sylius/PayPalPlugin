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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Controller\PayPalRedirectReturnAction;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalRedirectReturnActionTest extends TestCase
{
    private OrderProviderInterface&MockObject $orderProvider;

    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private PayerActionCheckerInterface&MockObject $payerActionChecker;

    private UrlGeneratorInterface&MockObject $router;

    private FlashBag $flashBag;

    private OrderInterface&MockObject $order;

    private PayPalRedirectReturnAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderProvider = $this->createMock(OrderProviderInterface::class);
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);
        $this->payerActionChecker = $this->createMock(PayerActionCheckerInterface::class);
        $this->router = $this->createMock(UrlGeneratorInterface::class);

        $this->payerActionChecker->method('matchesPayerActionReturnNonce')->willReturn(true);

        $this->order = $this->createMock(OrderInterface::class);
        $this->order->method('getTokenValue')->willReturn('ORDER_TOKEN');
        $this->orderProvider->method('provideOrderByToken')->with('ORDER_TOKEN')->willReturn($this->order);

        $this->flashBag = new FlashBag();
        $session = new Session(new MockArraySessionStorage(), null, $this->flashBag);
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->router->method('generate')->willReturnCallback(
            static fn (string $route): string => 'https://shop.example.com/' . $route,
        );

        $this->action = new PayPalRedirectReturnAction(
            $this->orderProvider,
            $this->paymentSettlementProcessor,
            $this->payerActionChecker,
            $this->router,
            $requestStack,
        );
    }

    public function test_it_settles_the_payment_the_payer_left_processing(): void
    {
        $payment = $this->payment(PaymentInterface::STATE_PROCESSING);

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($payment);

        ($this->action)($this->request());
    }

    public function test_it_thanks_the_payer_once_the_capture_has_completed(): void
    {
        $this->payment(PaymentInterface::STATE_COMPLETED);

        $response = ($this->action)($this->request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://shop.example.com/sylius_shop_order_thank_you', $response->getTargetUrl());
        self::assertSame([], $this->flashBag->peekAll());
    }

    public function test_it_tells_the_payer_the_bank_transfer_is_still_on_its_way(): void
    {
        $this->payment(PaymentInterface::STATE_PROCESSING);

        $response = ($this->action)($this->request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['sylius_paypal.payment_pending'], $this->flashBag->peek('info'));
    }

    public function test_it_never_sends_a_payer_whose_money_is_in_flight_back_to_the_payment_form(): void
    {
        $this->payment(PaymentInterface::STATE_PROCESSING);

        $response = ($this->action)($this->request());

        self::assertSame('https://shop.example.com/sylius_shop_order_thank_you', $response->getTargetUrl());
    }

    public function test_it_tells_the_payer_the_bank_refused(): void
    {
        $this->payment(PaymentInterface::STATE_FAILED);

        ($this->action)($this->request());

        self::assertSame(['sylius_paypal.something_went_wrong'], $this->flashBag->peek('error'));
    }

    public function test_it_does_not_settle_an_order_with_nothing_in_flight(): void
    {
        $this->order->method('getLastPayment')->willReturn(null);

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');

        $response = ($this->action)($this->request());

        self::assertSame('https://shop.example.com/sylius_shop_order_show', $response->getTargetUrl());
    }

    public function test_it_answers_no_one_but_the_payer_action_that_started_the_payment(): void
    {
        $this->payment(PaymentInterface::STATE_PROCESSING);

        $payerActionChecker = $this->createMock(PayerActionCheckerInterface::class);
        $payerActionChecker->method('matchesPayerActionReturnNonce')->willReturn(false);

        $action = new PayPalRedirectReturnAction(
            $this->orderProvider,
            $this->paymentSettlementProcessor,
            $payerActionChecker,
            $this->router,
            new RequestStack(),
        );

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');

        $this->expectException(NotFoundHttpException::class);

        $action($this->request());
    }

    private function payment(string $state): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn($state);
        $this->order->method('getLastPayment')->willReturn($payment);

        return $payment;
    }

    private function request(): Request
    {
        $request = new Request();
        $request->attributes->set('token', 'ORDER_TOKEN');
        $request->attributes->set('nonce', 'NONCE');

        return $request;
    }
}
