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
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CreatePayPalOrderActionTest extends TestCase
{
    private PaymentStateManagerInterface&MockObject $paymentStateManager;

    private OrderProviderInterface&Stub $orderProvider;

    private CapturePaymentResolverInterface&MockObject $capturePaymentResolver;

    private PayPalPaymentSourceProviderInterface&Stub $paymentSourceProvider;

    private OrderInterface&Stub $order;

    private CreatePayPalOrderAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentStateManager = $this->createMock(PaymentStateManagerInterface::class);
        $this->orderProvider = $this->createStub(OrderProviderInterface::class);
        $this->capturePaymentResolver = $this->createMock(CapturePaymentResolverInterface::class);
        $this->paymentSourceProvider = $this->createStub(PayPalPaymentSourceProviderInterface::class);
        $this->order = $this->createStub(OrderInterface::class);

        $this->paymentSourceProvider
            ->method('supports')
            ->willReturnCallback(static fn (string $paymentSource): bool => in_array($paymentSource, ['paypal', 'google_pay'], true))
        ;

        $this->orderProvider->method('provideOrderByToken')->with('ORDER_TOKEN')->willReturn($this->order);

        $this->action = new CreatePayPalOrderAction(
            $this->paymentStateManager,
            $this->orderProvider,
            $this->capturePaymentResolver,
            $this->paymentSourceProvider,
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

    public function test_it_records_the_requested_payment_source_on_the_payment(): void
    {
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: null, new: $payment);

        $payment
            ->expects(self::once())
            ->method('setDetails')
            ->with(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'payment_source' => 'google_pay'])
        ;

        ($this->action)($this->request('{"paymentSource":"google_pay"}'));
    }

    public function test_it_records_paypal_as_the_payment_source_when_the_request_names_none(): void
    {
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: null, new: $payment);

        $payment
            ->expects(self::once())
            ->method('setDetails')
            ->with(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'payment_source' => 'paypal'])
        ;

        ($this->action)($this->request());
    }

    public function test_it_falls_back_to_paypal_when_the_request_body_is_not_valid_json(): void
    {
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: null, new: $payment);

        $payment
            ->expects(self::once())
            ->method('setDetails')
            ->with(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'payment_source' => 'paypal'])
        ;

        self::assertSame(Response::HTTP_OK, ($this->action)($this->request('not json'))->getStatusCode());
    }

    public function test_it_answers_with_an_unprocessable_entity_when_the_payment_source_is_not_supported(): void
    {
        $this->payments(processing: $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME), new: null);

        $this->paymentStateManager->expects(self::never())->method('cancel');
        $this->capturePaymentResolver->expects(self::never())->method('resolve');

        self::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ($this->action)($this->request('{"paymentSource":"bitcoin"}'))->getStatusCode(),
        );
    }

    public function test_it_still_accepts_a_real_payment_source_without_an_injected_provider(): void
    {
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: null, new: $payment);

        $action = new CreatePayPalOrderAction(
            $this->paymentStateManager,
            $this->orderProvider,
            $this->capturePaymentResolver,
        );

        self::assertSame(
            Response::HTTP_OK,
            $action($this->request('{"paymentSource":"card"}'))->getStatusCode(),
        );
    }

    public function test_it_hands_the_browser_the_link_paypal_wants_the_payer_sent_to(): void
    {
        $this->payments(processing: null, new: $this->payment(
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME,
            ['payer_action_url' => 'https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID'],
        ));

        $content = json_decode((string) ($this->action)($this->request())->getContent(), true);

        self::assertSame(
            'https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID',
            $content['payerActionUrl'],
        );
    }

    public function test_it_sends_no_payer_action_url_for_a_payment_that_has_none(): void
    {
        $this->payments(processing: null, new: $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME));

        $content = json_decode((string) ($this->action)($this->request())->getContent(), true);

        self::assertArrayNotHasKey('payerActionUrl', $content);
    }

    public function test_it_refuses_to_send_the_browser_anywhere_but_paypal(): void
    {
        foreach ([
            'https://paypal.com.evil.example.com/payment/trustly',
            'http://www.paypal.com/payment/trustly',
            'https://evil.example.com/payment/trustly',
        ] as $url) {
            $order = $this->createStub(OrderInterface::class);
            $order->method('getLastPayment')->willReturnCallback(
                fn (?string $state = null): ?PaymentInterface => PaymentInterface::STATE_NEW === $state
                    ? $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME, ['payer_action_url' => $url])
                    : null,
            );

            $orderProvider = $this->createStub(OrderProviderInterface::class);
            $orderProvider->method('provideOrderByToken')->willReturn($order);

            $action = new CreatePayPalOrderAction(
                $this->paymentStateManager,
                $orderProvider,
                $this->capturePaymentResolver,
                $this->paymentSourceProvider,
            );

            $content = json_decode((string) $action($this->request())->getContent(), true);

            self::assertArrayNotHasKey('payerActionUrl', $content, $url);
        }
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

    /** @param array<string, mixed> $details */
    private function payment(string $factoryName, array $details = []): PaymentInterface&MockObject
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_NEW);
        $payment->method('getDetails')->willReturn(array_merge(['paypal_order_id' => 'PAYPAL_ORDER_ID'], $details));

        return $payment;
    }

    private function request(?string $content = null): Request
    {
        return new Request([], [], ['token' => 'ORDER_TOKEN'], [], [], [], $content);
    }
}
