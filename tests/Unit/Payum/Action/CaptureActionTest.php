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
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class CaptureActionTest extends TestCase
{
    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private CreateOrderApiInterface&MockObject $createOrderApi;

    private UuidProviderInterface&MockObject $uuidProvider;

    private CaptureAction $captureAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->createOrderApi = $this->createMock(CreateOrderApiInterface::class);
        $this->uuidProvider = $this->createMock(UuidProviderInterface::class);

        $this->captureAction = new CaptureAction(
            $this->authorizeClientApi,
            $this->createOrderApi,
            $this->uuidProvider,
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
}
