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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessor;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final class PaymentCaptureProcessorTest extends TestCase
{
    private const ORDER_DETAILS = [
        'status' => 'COMPLETED',
        'id' => '123123',
        'purchase_units' => [['reference_id' => 'REFERENCE_ID']],
    ];

    private UpdateOrderApiInterface&MockObject $updateOrderApi;

    private UpdateOrderAddressApiInterface&MockObject $updateOrderAddressApi;

    private CompleteOrderApiInterface&MockObject $completeOrderApi;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private PaymentUpdaterInterface&MockObject $paymentUpdater;

    private StateResolverInterface&MockObject $orderPaymentStateResolver;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private OrderInterface&MockObject $order;

    private PaymentInterface&MockObject $payment;

    private PaymentCaptureProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->updateOrderApi = $this->createMock(UpdateOrderApiInterface::class);
        $this->updateOrderAddressApi = $this->createMock(UpdateOrderAddressApiInterface::class);
        $this->completeOrderApi = $this->createMock(CompleteOrderApiInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->paymentUpdater = $this->createMock(PaymentUpdaterInterface::class);
        $this->orderPaymentStateResolver = $this->createMock(StateResolverInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $authorizeClientApi->method('authorize')->with($this->paymentMethod)->willReturn('TOKEN');
        $this->payment->method('getMethod')->willReturn($this->paymentMethod);
        $this->payment->method('getOrder')->willReturn($this->order);

        $this->processor = new PaymentCaptureProcessor(
            $authorizeClientApi,
            $this->updateOrderApi,
            $this->updateOrderAddressApi,
            $this->completeOrderApi,
            $this->orderDetailsApi,
            $this->paymentUpdater,
            $this->orderPaymentStateResolver,
        );
    }

    public function test_it_implements_payment_capture_processor_interface(): void
    {
        self::assertInstanceOf(PaymentCaptureProcessorInterface::class, $this->processor);
    }

    public function test_it_captures_the_paypal_order_and_records_it_on_the_payment(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1000);

        $this->updateOrderApi->expects(self::never())->method('update');
        $this->completeOrderApi->expects(self::once())->method('complete')->with('TOKEN', '123123');
        $this->orderDetailsApi->method('get')->with('TOKEN', '123123')->willReturn(self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => PayPalPaymentStatus::Completed->value,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'paypal',
        ]);

        self::assertSame(self::ORDER_DETAILS, $this->processor->capture($this->payment));
    }

    public function test_it_records_a_capture_paypal_has_not_completed_yet_as_processing(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123'], amount: 1000, total: 1000);

        $this->orderDetailsApi->method('get')->willReturn(['status' => 'APPROVED'] + self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails')->with(self::callback(
            fn (array $details): bool => PayPalPaymentStatus::Processing->value === $details['status'],
        ));

        $this->processor->capture($this->payment);
    }

    public function test_it_carries_the_payment_source_and_the_transaction_id_through_the_capture(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'payment_source' => 'google_pay'], amount: 1000, total: 1000);

        $this->orderDetailsApi->method('get')->willReturn([
            'status' => 'COMPLETED',
            'id' => '123123',
            'purchase_units' => [['reference_id' => 'REFERENCE_ID', 'payments' => ['captures' => [['id' => 'TRANSACTION_ID']]]]],
        ]);
        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => PayPalPaymentStatus::Completed->value,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'google_pay',
            'transaction_id' => 'TRANSACTION_ID',
        ]);

        $this->processor->capture($this->payment);
    }

    public function test_it_brings_the_paypal_order_up_to_the_order_total_before_capturing(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1200);

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['merchant_id' => 'MERCHANT_ID', 'sylius_merchant_id' => 'SYLIUS_MERCHANT_ID']);
        $this->paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->updateOrderApi->expects(self::once())->method('update')->with('TOKEN', '123123', $this->payment, 'REFERENCE_ID', 'MERCHANT_ID');
        $this->paymentUpdater->expects(self::once())->method('updateAmount')->with($this->payment, 1200);
        $this->orderPaymentStateResolver->expects(self::once())->method('resolve')->with($this->order);
        $this->orderDetailsApi->method('get')->willReturn(self::ORDER_DETAILS);

        $this->processor->capture($this->payment);
    }

    public function test_it_sends_paypal_the_shipping_address_of_an_order_that_needs_shipping(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1000, shippingRequired: true);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $this->order->method('getShippingAddress')->willReturn($shippingAddress);

        $this->updateOrderAddressApi->expects(self::once())->method('update')->with('TOKEN', '123123', 'REFERENCE_ID', $shippingAddress);
        $this->orderDetailsApi->method('get')->willReturn(self::ORDER_DETAILS);

        $this->processor->capture($this->payment);
    }

    public function test_it_sends_paypal_no_shipping_address_for_an_order_that_needs_no_shipping(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123'], amount: 1000, total: 1000);

        $this->updateOrderAddressApi->expects(self::never())->method('update');
        $this->orderDetailsApi->method('get')->willReturn(self::ORDER_DETAILS);

        $this->processor->capture($this->payment);
    }

    /** @param array<string, mixed> $details */
    private function paymentOf(array $details, int $amount, int $total, bool $shippingRequired = false): void
    {
        $this->payment->method('getDetails')->willReturn($details);
        $this->payment->method('getAmount')->willReturn($amount);
        $this->order->method('getTotal')->willReturn($total);
        $this->order->method('isShippingRequired')->willReturn($shippingRequired);
    }
}
