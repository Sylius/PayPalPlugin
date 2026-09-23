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

namespace Tests\Sylius\PayPalPlugin\Unit\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Payum\Action\CaptureAction;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Provider\NonceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProvider;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class CaptureActionTest extends TestCase
{
    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private CreateOrderApiInterface&MockObject $createOrderApi;

    private UuidProviderInterface&MockObject $uuidProvider;

    private NonceProviderInterface&MockObject $nonceProvider;

    private CaptureAction $captureAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->createOrderApi = $this->createMock(CreateOrderApiInterface::class);
        $this->uuidProvider = $this->createMock(UuidProviderInterface::class);
        $this->nonceProvider = $this->createMock(NonceProviderInterface::class);

        $this->nonceProvider->method('provide')->willReturnOnConsecutiveCalls('RETURN_NONCE', 'CANCEL_NONCE');

        $this->captureAction = new CaptureAction(
            $this->authorizeClientApi,
            $this->createOrderApi,
            $this->uuidProvider,
            new PayPalOrderCreatedStatusesProvider(),
            $this->nonceProvider,
        );
    }

    #[Test]
    public function it_implements_action_interface(): void
    {
        self::assertInstanceOf(ActionInterface::class, $this->captureAction);
    }

    #[Test]
    public function it_authorizes_seller_send_create_order_request_and_sets_order_response_data_on_payment(): void
    {
        $request = $this->createMock(Capture::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAmount')->willReturn(1000);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('USD');

        $this->uuidProvider->method('provide')->willReturn('UUID');

        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('ACCESS_TOKEN');
        $this->createOrderApi->method('create')->with('ACCESS_TOKEN', $payment, 'UUID')->willReturn(['status' => 'CREATED', 'id' => '123123']);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_CAPTURED,
            'paypal_order_id' => '123123',
            'reference_id' => 'UUID',
            'payment_amount' => 1000,
            'payment_source' => 'paypal',
        ]);

        $this->captureAction->execute($request);
    }

    public function test_it_creates_the_order_with_the_payment_source_recorded_on_the_payment(): void
    {
        $request = $this->createMock(Capture::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAmount')->willReturn(1000);
        $payment->method('getDetails')->willReturn(['status' => 'CREATED', 'payment_source' => 'google_pay']);

        $this->uuidProvider->method('provide')->willReturn('UUID');
        $this->authorizeClientApi->method('authorize')->willReturn('ACCESS_TOKEN');

        $this->createOrderApi
            ->expects(self::once())
            ->method('create')
            ->with('ACCESS_TOKEN', $payment, 'UUID', 'google_pay')
            ->willReturn(['status' => 'CREATED', 'id' => '123123'])
        ;

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_CAPTURED,
            'paypal_order_id' => '123123',
            'reference_id' => 'UUID',
            'payment_amount' => 1000,
            'payment_source' => 'google_pay',
        ]);

        $this->captureAction->execute($request);
    }

    #[Test]
    public function it_sets_order_response_data_on_payment_when_paypal_asks_for_a_payer_action(): void
    {
        $request = $this->createMock(Capture::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAmount')->willReturn(1000);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('USD');

        $this->uuidProvider->method('provide')->willReturn('UUID');

        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('ACCESS_TOKEN');
        $this->createOrderApi->method('create')->with('ACCESS_TOKEN', $payment, 'UUID')->willReturn(['status' => 'PAYER_ACTION_REQUIRED', 'id' => '123123']);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_CAPTURED,
            'paypal_order_id' => '123123',
            'reference_id' => 'UUID',
            'payment_amount' => 1000,
            'payment_source' => 'paypal',
        ]);

        $this->captureAction->execute($request);
    }

    #[Test]
    public function it_does_not_set_order_response_data_on_payment_when_paypal_does_not_confirm_the_order(): void
    {
        $request = $this->createMock(Capture::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $this->uuidProvider->method('provide')->willReturn('UUID');

        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('ACCESS_TOKEN');
        $this->createOrderApi->method('create')->with('ACCESS_TOKEN', $payment, 'UUID')->willReturn(['name' => 'UNPROCESSABLE_ENTITY']);

        $payment->expects(self::never())->method('setDetails');

        $this->captureAction->execute($request);
    }

    #[Test]
    public function it_treats_as_created_only_the_statuses_its_provider_names(): void
    {
        $orderCreatedStatusesProvider = $this->createMock(PayPalOrderCreatedStatusesProviderInterface::class);
        $orderCreatedStatusesProvider->method('provide')->willReturn(['SOME_OTHER_STATUS']);

        $captureAction = new CaptureAction(
            $this->authorizeClientApi,
            $this->createOrderApi,
            $this->uuidProvider,
            $orderCreatedStatusesProvider,
        );

        $request = $this->createMock(Capture::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('ACCESS_TOKEN');
        $this->uuidProvider->method('provide')->willReturn('UUID');
        $this->createOrderApi->method('create')->willReturn(['status' => 'CREATED', 'id' => '123123']);

        $payment->expects(self::never())->method('setDetails');

        $captureAction->execute($request);
    }

    #[Test]
    public function it_throws_an_exception_if_request_type_is_invalid(): void
    {
        $request = $this->createMock(Authorize::class);

        $this->expectException(RequestNotSupportedException::class);
        $this->captureAction->execute($request);
    }

    #[Test]
    public function it_supports_capture_request_with_payment_as_first_model(): void
    {
        $request = $this->createMock(Capture::class);
        $payment = $this->createMock(PaymentInterface::class);

        $request->method('getModel')->willReturn($payment);

        self::assertTrue($this->captureAction->supports($request));
    }

    #[Test]
    public function it_does_not_support_request_other_than_capture(): void
    {
        $request = $this->createMock(GetStatus::class);

        self::assertFalse($this->captureAction->supports($request));
    }

    #[Test]
    public function it_does_not_support_request_with_first_model_other_than_payment(): void
    {
        $request = $this->createMock(Capture::class);
        $request->method('getModel')->willReturn('badObject');

        self::assertFalse($this->captureAction->supports($request));
    }

    public function test_it_keeps_the_link_paypal_sends_the_payer_to(): void
    {
        $request = $this->createMock(Capture::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAmount')->willReturn(1000);
        $payment->method('getDetails')->willReturn(['payment_source' => 'trustly']);

        $this->uuidProvider->method('provide')->willReturn('UUID');
        $this->authorizeClientApi->method('authorize')->willReturn('ACCESS_TOKEN');
        $this->createOrderApi->method('create')->willReturn([
            'status' => 'PAYER_ACTION_REQUIRED',
            'id' => '123123',
            'links' => [
                ['href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/123123', 'rel' => 'self', 'method' => 'GET'],
                ['href' => 'https://www.sandbox.paypal.com/payment/trustly?token=123123', 'rel' => 'payer-action', 'method' => 'GET'],
            ],
        ]);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_CAPTURED,
            'paypal_order_id' => '123123',
            'reference_id' => 'UUID',
            'payment_amount' => 1000,
            'payment_source' => 'trustly',
            'payer_action_url' => 'https://www.sandbox.paypal.com/payment/trustly?token=123123',
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
        ]);

        $this->captureAction->execute($request);
    }

    public function test_it_keeps_no_payer_action_url_when_paypal_sends_no_such_link(): void
    {
        $request = $this->createMock(Capture::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAmount')->willReturn(1000);
        $payment->method('getDetails')->willReturn([]);

        $this->uuidProvider->method('provide')->willReturn('UUID');
        $this->authorizeClientApi->method('authorize')->willReturn('ACCESS_TOKEN');
        $this->createOrderApi->method('create')->willReturn([
            'status' => 'CREATED',
            'id' => '123123',
            'links' => [
                ['href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/123123', 'rel' => 'self', 'method' => 'GET'],
                ['href' => 'https://www.sandbox.paypal.com/checkoutnow?token=123123', 'rel' => 'approve', 'method' => 'GET'],
            ],
        ]);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_CAPTURED,
            'paypal_order_id' => '123123',
            'reference_id' => 'UUID',
            'payment_amount' => 1000,
            'payment_source' => 'paypal',
        ]);

        $this->captureAction->execute($request);
    }

    public function test_it_sends_paypal_a_payer_action_nonce_only_for_a_redirect_payment_source(): void
    {
        $this->uuidProvider->method('provide')->willReturn('UUID');
        $this->authorizeClientApi->method('authorize')->willReturn('ACCESS_TOKEN');

        $this->createOrderApi
            ->expects(self::exactly(2))
            ->method('create')
            ->willReturnCallback(function (
                string $token,
                PaymentInterface $payment,
                string $referenceId,
                string $paymentSource,
                ?string $payerActionReturnNonce,
                ?string $payerActionCancelNonce,
            ): array {
                $redirect = 'trustly' === $paymentSource;

                self::assertSame($redirect ? 'RETURN_NONCE' : null, $payerActionReturnNonce);
                self::assertSame($redirect ? 'CANCEL_NONCE' : null, $payerActionCancelNonce);

                return ['status' => 'CREATED', 'id' => '123123'];
            })
        ;

        $this->captureAction->execute($this->captureOf(['payment_source' => 'trustly']));
        $this->captureAction->execute($this->captureOf([]));
    }

    /** @param array<string, mixed> $details */
    private function captureOf(array $details): Capture&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));
        $payment->method('getAmount')->willReturn(1000);
        $payment->method('getDetails')->willReturn($details);

        $request = $this->createMock(Capture::class);
        $request->method('getModel')->willReturn($payment);

        return $request;
    }
}
