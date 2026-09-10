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

namespace Tests\Sylius\PayPalPlugin\Unit\Factory;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactory;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;

final class PayPalPurchaseUnitFactoryTest extends TestCase
{
    private PaymentReferenceNumberProviderInterface&MockObject $paymentReferenceNumberProvider;

    private PayPalItemDataProviderInterface&MockObject $payPalItemDataProvider;

    private OrderInterface&MockObject $order;

    private GatewayConfigInterface&MockObject $gatewayConfig;

    private PaymentInterface&MockObject $payment;

    private PayPalPurchaseUnitFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentReferenceNumberProvider = $this->createMock(PaymentReferenceNumberProviderInterface::class);
        $this->payPalItemDataProvider = $this->createMock(PayPalItemDataProviderInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($this->gatewayConfig);

        $this->payment->method('getOrder')->willReturn($this->order);
        $this->payment->method('getMethod')->willReturn($paymentMethod);
        $this->payment->method('getAmount')->willReturn(10000);

        $this->order->method('getCurrencyCode')->willReturn('PLN');
        $this->order->method('getShippingTotal')->willReturn(1000);
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->order->method('getOrderPromotionTotal')->willReturn(0);
        $this->order
            ->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT)
            ->willReturn(0);

        $this->gatewayConfig->method('getConfig')->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id'],
        );

        $this->paymentReferenceNumberProvider->method('provide')->with($this->payment)->willReturn('REFERENCE-NUMBER');
        $this->payPalItemDataProvider->method('provide')->with($this->order)->willReturn([
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => ['value' => '90.00', 'currency_code' => 'PLN'],
                    'quantity' => 1,
                    'tax' => ['value' => '0.00', 'currency_code' => 'PLN'],
                ],
            ],
            'total_item_value' => '90.00',
            'total_tax' => '0.00',
        ]);

        $this->factory = new PayPalPurchaseUnitFactory(
            $this->paymentReferenceNumberProvider,
            $this->payPalItemDataProvider,
        );
    }

    public function test_it_implements_paypal_purchase_unit_factory_interface(): void
    {
        self::assertInstanceOf(PayPalPurchaseUnitFactoryInterface::class, $this->factory);
    }

    public function test_it_builds_the_purchase_unit_from_the_payment_and_its_order(): void
    {
        $purchaseUnit = $this->factory->create($this->payment, 'REFERENCE_ID')->toArray();

        self::assertSame('REFERENCE_ID', $purchaseUnit['reference_id']);
        self::assertSame('REFERENCE-NUMBER', $purchaseUnit['invoice_id']);
        self::assertSame('PLN', $purchaseUnit['amount']['currency_code']);
        self::assertSame('100.00', $purchaseUnit['amount']['value']);
        self::assertSame(
            ['currency_code' => 'PLN', 'value' => '10.00'],
            $purchaseUnit['amount']['breakdown']['shipping'],
        );
        self::assertSame(
            ['currency_code' => 'PLN', 'value' => '90.00'],
            $purchaseUnit['amount']['breakdown']['item_total'],
        );
        self::assertSame('PRODUCT_ONE', $purchaseUnit['items'][0]['name']);
        self::assertSame('90.00', $purchaseUnit['items'][0]['unit_amount']['value']);
    }

    public function test_it_takes_the_merchant_id_from_the_payment_method_gateway_config(): void
    {
        $purchaseUnit = $this->factory->create($this->payment, 'REFERENCE_ID')->toArray();

        self::assertSame('merchant-id', $purchaseUnit['payee']['merchant_id']);
    }

    public function test_it_prefers_the_merchant_id_it_is_given_over_the_configured_one(): void
    {
        $purchaseUnit = $this->factory->create($this->payment, 'REFERENCE_ID', 'other-merchant-id')->toArray();

        self::assertSame('other-merchant-id', $purchaseUnit['payee']['merchant_id']);
    }

    public function test_it_sends_the_shipping_address_when_the_order_carries_one(): void
    {
        $shippingAddress = $this->createMock(AddressInterface::class);
        $shippingAddress->method('getFullName')->willReturn('Gandalf The Grey');
        $shippingAddress->method('getStreet')->willReturn('Hobbit St. 123');
        $shippingAddress->method('getCity')->willReturn('Minas Tirith');
        $shippingAddress->method('getPostcode')->willReturn('000');
        $shippingAddress->method('getCountryCode')->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getShippingTotal')->willReturn(1000);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')->willReturn(0);

        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($this->gatewayConfig);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAmount')->willReturn(10000);

        $paymentReferenceNumberProvider = $this->createMock(PaymentReferenceNumberProviderInterface::class);
        $paymentReferenceNumberProvider->method('provide')->willReturn('REFERENCE-NUMBER');
        $payPalItemDataProvider = $this->createMock(PayPalItemDataProviderInterface::class);
        $payPalItemDataProvider->method('provide')->willReturn([
            'items' => [],
            'total_item_value' => '90.00',
            'total_tax' => '0.00',
        ]);

        $factory = new PayPalPurchaseUnitFactory($paymentReferenceNumberProvider, $payPalItemDataProvider);
        $purchaseUnit = $factory->create($payment, 'REFERENCE_ID')->toArray();

        self::assertSame('Gandalf The Grey', $purchaseUnit['shipping']['name']['full_name']);
        self::assertSame('Hobbit St. 123', $purchaseUnit['shipping']['address']['address_line_1']);
        self::assertSame('Minas Tirith', $purchaseUnit['shipping']['address']['admin_area_2']);
        self::assertSame('000', $purchaseUnit['shipping']['address']['postal_code']);
        self::assertSame('US', $purchaseUnit['shipping']['address']['country_code']);
    }

    public function test_it_fails_when_the_gateway_config_carries_no_merchant_id(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['sylius_merchant_id' => 'sylius-merchant-id']);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($this->order);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAmount')->willReturn(10000);

        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create($payment, 'REFERENCE_ID');
    }
}
