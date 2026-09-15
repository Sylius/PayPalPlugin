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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderAction;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CreatePayPalOrderActionTest extends TestCase
{
    private PaymentStateManagerInterface&MockObject $paymentStateManager;

    private OrderProviderInterface&Stub $orderProvider;

    private CapturePaymentResolverInterface&MockObject $capturePaymentResolver;

    private OrderInterface&Stub $order;

    private CreatePayPalOrderAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentStateManager = $this->createMock(PaymentStateManagerInterface::class);
        $this->orderProvider = $this->createStub(OrderProviderInterface::class);
        $this->capturePaymentResolver = $this->createMock(CapturePaymentResolverInterface::class);
        $this->order = $this->createStub(OrderInterface::class);

        $this->orderProvider->method('provideOrderByToken')->with('ORDER_TOKEN')->willReturn($this->order);

        $this->action = new CreatePayPalOrderAction(
            $this->paymentStateManager,
            $this->orderProvider,
            $this->capturePaymentResolver,
        );
    }

    public function test_it_creates_a_paypal_order_for_the_payment_awaiting_payment(): void
    {
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: null, new: $payment);

        $this->capturePaymentResolver->expects(self::once())->method('resolve')->with($payment);
        $this->paymentStateManager->expects(self::once())->method('process')->with($payment);

        self::assertSame(Response::HTTP_OK, ($this->action)($this->request())->getStatusCode());
    }

    public function test_it_answers_with_the_paypal_order_id_under_both_the_new_and_the_legacy_key(): void
    {
        $this->payments(processing: null, new: $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME));

        $content = json_decode((string) ($this->action)($this->request())->getContent(), true);

        self::assertSame('PAYPAL_ORDER_ID', $content['orderId']);
        self::assertSame('PAYPAL_ORDER_ID', $content['orderID']);
        self::assertSame(PaymentInterface::STATE_NEW, $content['status']);
    }

    public function test_it_cancels_a_live_paypal_attempt_before_creating_a_new_one(): void
    {
        $livePayment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: $livePayment, new: $payment);

        $this->paymentStateManager->expects(self::once())->method('cancel')->with($livePayment);
        $this->capturePaymentResolver->expects(self::once())->method('resolve')->with($payment);

        ($this->action)($this->request());
    }

    public function test_it_leaves_a_processing_payment_of_another_gateway_alone(): void
    {
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: $this->payment('offline'), new: $payment);

        $this->paymentStateManager->expects(self::never())->method('cancel');
        $this->capturePaymentResolver->expects(self::once())->method('resolve')->with($payment);

        ($this->action)($this->request());
    }

    public function test_it_answers_with_a_conflict_when_no_payment_awaits_payment(): void
    {
        $this->payments(processing: null, new: null);

        $this->capturePaymentResolver->expects(self::never())->method('resolve');
        $this->paymentStateManager->expects(self::never())->method('process');

        self::assertSame(Response::HTTP_CONFLICT, ($this->action)($this->request())->getStatusCode());
    }

    private function payments(?PaymentInterface $processing, ?PaymentInterface $new): void
    {
        $this->order->method('getLastPayment')->willReturnCallback(
            static fn (?string $state = null): ?PaymentInterface => match ($state) {
                PaymentInterface::STATE_PROCESSING => $processing,
                PaymentInterface::STATE_NEW => $new,
                default => null,
            },
        );
    }

    private function payment(string $factoryName): PaymentInterface&Stub
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_NEW);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        return $payment;
    }

    private function request(): Request
    {
        return new Request([], [], ['token' => 'ORDER_TOKEN']);
    }
}
