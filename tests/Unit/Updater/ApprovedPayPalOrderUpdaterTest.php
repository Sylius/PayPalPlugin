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

namespace Tests\Sylius\PayPalPlugin\Unit\Updater;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdater;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final class ApprovedPayPalOrderUpdaterTest extends TestCase
{
    private UpdateOrderApiInterface&MockObject $updateOrderApi;

    private UpdateOrderAddressApiInterface&MockObject $updateOrderAddressApi;

    private PaymentUpdaterInterface&MockObject $paymentUpdater;

    private StateResolverInterface&MockObject $orderPaymentStateResolver;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private OrderInterface&MockObject $order;

    private PaymentInterface&MockObject $payment;

    private ApprovedPayPalOrderUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();
        $this->updateOrderApi = $this->createMock(UpdateOrderApiInterface::class);
        $this->updateOrderAddressApi = $this->createMock(UpdateOrderAddressApiInterface::class);
        $this->paymentUpdater = $this->createMock(PaymentUpdaterInterface::class);
        $this->orderPaymentStateResolver = $this->createMock(StateResolverInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->payment->method('getMethod')->willReturn($this->paymentMethod);
        $this->payment->method('getOrder')->willReturn($this->order);
        $this->payment->method('getDetails')->willReturn(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID']);

        $this->updater = new ApprovedPayPalOrderUpdater(
            $this->updateOrderApi,
            $this->updateOrderAddressApi,
            $this->paymentUpdater,
            $this->orderPaymentStateResolver,
        );
    }

    public function test_it_implements_approved_paypal_order_updater_interface(): void
    {
        self::assertInstanceOf(ApprovedPayPalOrderUpdaterInterface::class, $this->updater);
    }

    public function test_it_brings_the_paypal_order_up_to_the_order_total(): void
    {
        $this->paymentOf(amount: 1000, total: 1200);

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['merchant_id' => 'MERCHANT_ID']);
        $this->paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->updateOrderApi->expects(self::once())->method('update')->with($this->paymentMethod, '123123', $this->payment, 'REFERENCE_ID', 'MERCHANT_ID');
        $this->paymentUpdater->expects(self::once())->method('updateAmount')->with($this->payment, 1200);
        $this->orderPaymentStateResolver->expects(self::once())->method('resolve')->with($this->order);

        $this->updater->update($this->payment);
    }

    public function test_it_leaves_the_amount_of_a_paypal_order_matching_the_order_total_alone(): void
    {
        $this->paymentOf(amount: 1000, total: 1000);

        $this->updateOrderApi->expects(self::never())->method('update');
        $this->paymentUpdater->expects(self::never())->method('updateAmount');
        $this->orderPaymentStateResolver->expects(self::never())->method('resolve');

        $this->updater->update($this->payment);
    }

    public function test_it_sends_paypal_the_shipping_address_of_an_order_that_needs_shipping(): void
    {
        $this->paymentOf(amount: 1000, total: 1000, shippingRequired: true);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $this->order->method('getShippingAddress')->willReturn($shippingAddress);

        $this->updateOrderAddressApi->expects(self::once())->method('update')->with($this->paymentMethod, '123123', 'REFERENCE_ID', $shippingAddress);

        $this->updater->update($this->payment);
    }

    public function test_it_sends_paypal_no_shipping_address_for_an_order_that_needs_shipping_but_has_none(): void
    {
        $this->paymentOf(amount: 1000, total: 1000, shippingRequired: true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $this->updateOrderAddressApi->expects(self::never())->method('update');

        $this->updater->update($this->payment);
    }

    public function test_it_sends_paypal_no_shipping_address_for_an_order_that_needs_no_shipping(): void
    {
        $this->paymentOf(amount: 1000, total: 1000);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));

        $this->updateOrderAddressApi->expects(self::never())->method('update');

        $this->updater->update($this->payment);
    }

    private function paymentOf(int $amount, int $total, bool $shippingRequired = false): void
    {
        $this->payment->method('getAmount')->willReturn($amount);
        $this->order->method('getTotal')->willReturn($total);
        $this->order->method('isShippingRequired')->willReturn($shippingRequired);
    }
}
