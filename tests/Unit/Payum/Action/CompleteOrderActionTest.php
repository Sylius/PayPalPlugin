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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Payum\Action\CompleteOrderAction;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final class CompleteOrderActionTest extends TestCase
{
    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private UpdateOrderApiInterface&MockObject $updateOrderApi;

    private CompleteOrderApiInterface&MockObject $completeOrderApi;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private PaymentUpdaterInterface $payPalPaymentUpdater;

    private StateResolverInterface $orderPaymentStateResolver;

    private CompleteOrderAction $completeOrderAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->updateOrderApi = $this->createMock(UpdateOrderApiInterface::class);
        $this->completeOrderApi = $this->createMock(CompleteOrderApiInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->payPalPaymentUpdater = $this->createMock(PaymentUpdaterInterface::class);
        $this->orderPaymentStateResolver = $this->createMock(StateResolverInterface::class);

        $this->completeOrderAction = new CompleteOrderAction(
            $this->authorizeClientApi,
            $this->updateOrderApi,
            $this->completeOrderApi,
            $this->orderDetailsApi,
            null,
            $this->payPalPaymentUpdater,
            $this->orderPaymentStateResolver,
            null,
        );
    }

    #[Test]
    public function it_implements_action_interface(): void
    {
        self::assertInstanceOf(ActionInterface::class, $this->completeOrderAction);
    }

    #[Test]
    public function it_completes_order(): void
    {
        $request = $this->createMock(CompleteOrder::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn([]);
        $payment->method('getOrder')->willReturn($order);

        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('TOKEN');

        $request->method('getOrderId')->willReturn('123123');

        $payment->method('getAmount')->willReturn(1000);
        $order->method('getTotal')->willReturn(1000);

        $this->completeOrderApi->expects(self::once())->method('complete')->with('TOKEN', '123123');
        $this->orderDetailsApi->method('get')->with('TOKEN', '123123')->willReturn([
            'status' => 'COMPLETED',
            'id' => '123123',
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
        ]);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_COMPLETED,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'paypal',
        ]);

        $order->method('isShippingRequired')->willReturn(false);

        $this->completeOrderAction->execute($request);
    }

    public function test_it_carries_a_non_paypal_payment_source_through_completion(): void
    {
        $request = $this->createMock(CompleteOrder::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $request->method('getModel')->willReturn($payment);
        $request->method('getOrderId')->willReturn('123123');
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn(['payment_source' => 'google_pay']);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAmount')->willReturn(1000);
        $order->method('getTotal')->willReturn(1000);
        $order->method('isShippingRequired')->willReturn(false);

        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');
        $this->orderDetailsApi->method('get')->willReturn([
            'status' => 'COMPLETED',
            'id' => '123123',
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
        ]);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_COMPLETED,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'google_pay',
        ]);

        $this->completeOrderAction->execute($request);
    }

    #[Test]
    public function it_completes_order_and_saves_transaction_id(): void
    {
        $request = $this->createMock(CompleteOrder::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn([]);
        $payment->method('getOrder')->willReturn($order);

        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('TOKEN');

        $request->method('getOrderId')->willReturn('123123');

        $payment->method('getAmount')->willReturn(1000);
        $order->method('getTotal')->willReturn(1000);

        $this->completeOrderApi->expects(self::once())->method('complete')->with('TOKEN', '123123');
        $this->orderDetailsApi->method('get')->with('TOKEN', '123123')->willReturn([
            'status' => 'COMPLETED',
            'id' => '123123',
            'purchase_units' => [
                [
                    'reference_id' => 'REFERENCE_ID',
                    'payments' => ['captures' => [['id' => 'TRANSACTION_ID']]],
                ],
            ],
        ]);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_COMPLETED,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'paypal',
            'transaction_id' => 'TRANSACTION_ID',
        ]);

        $order->method('isShippingRequired')->willReturn(false);

        $this->completeOrderAction->execute($request);
    }

    #[Test]
    public function it_updates_paypal_shipping_address_and_completes_order(): void
    {
        $updateOrderAddressApi = $this->createMock(UpdateOrderAddressApiInterface::class);

        $completeOrderAction = new CompleteOrderAction(
            $this->authorizeClientApi,
            $this->updateOrderApi,
            $this->completeOrderApi,
            $this->orderDetailsApi,
            null,
            $this->payPalPaymentUpdater,
            $this->orderPaymentStateResolver,
            $updateOrderAddressApi,
        );

        $request = $this->createMock(CompleteOrder::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn([
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'paypal',
        ]);
        $payment->method('getOrder')->willReturn($order);

        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('TOKEN');

        $request->method('getOrderId')->willReturn('123123');

        $payment->method('getAmount')->willReturn(1000);
        $order->method('getTotal')->willReturn(1000);

        $this->completeOrderApi->expects(self::once())->method('complete')->with('TOKEN', '123123');
        $this->orderDetailsApi->method('get')->with('TOKEN', '123123')->willReturn([
            'status' => 'COMPLETED',
            'id' => '123123',
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
        ]);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => StatusAction::STATUS_COMPLETED,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'paypal',
        ]);

        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        $updateOrderAddressApi->expects(self::once())->method('update')->with('TOKEN', '123123', 'REFERENCE_ID', $shippingAddress);

        $completeOrderAction->execute($request);
    }

    public function test_it_never_captures_an_order_paypal_completes_on_payment_approval(): void
    {
        $request = $this->createMock(CompleteOrder::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn(['payment_source' => 'trustly']);

        $this->authorizeClientApi->expects(self::never())->method('authorize');
        $this->updateOrderApi->expects(self::never())->method('update');
        $this->completeOrderApi->expects(self::never())->method('complete');
        $this->orderDetailsApi->expects(self::never())->method('get');
        $payment->expects(self::never())->method('setDetails');

        $this->completeOrderAction->execute($request);
    }
}
