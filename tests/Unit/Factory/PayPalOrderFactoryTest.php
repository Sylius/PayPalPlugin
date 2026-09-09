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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactory;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalOrderFactoryTest extends TestCase
{
    private PayPalPurchaseUnitFactoryInterface&MockObject $payPalPurchaseUnitFactory;

    private UrlGeneratorInterface&MockObject $router;

    private OrderInterface&MockObject $order;

    private PaymentInterface&MockObject $payment;

    private string $shopScheme = 'https';

    private PayPalOrderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalPurchaseUnitFactory = $this->createMock(PayPalPurchaseUnitFactoryInterface::class);
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->payment->method('getOrder')->willReturn($this->order);
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $this->router->method('generate')->willReturnCallback($this->routeUrl(...));
        $this->payPalPurchaseUnitFactory->method('create')->willReturn($this->purchaseUnit());

        $this->factory = new PayPalOrderFactory($this->payPalPurchaseUnitFactory, $this->router);
    }

    public function test_it_implements_paypal_order_factory_interface(): void
    {
        self::assertInstanceOf(PayPalOrderFactoryInterface::class, $this->factory);
    }

    public function test_it_captures_and_delegates_the_purchase_unit_to_its_own_factory(): void
    {
        $payPalPurchaseUnitFactory = $this->createMock(PayPalPurchaseUnitFactoryInterface::class);
        $payPalPurchaseUnitFactory
            ->expects(self::once())
            ->method('create')
            ->with($this->payment, 'REFERENCE_ID')
            ->willReturn($this->purchaseUnit())
        ;

        $payPalOrder = (new PayPalOrderFactory($payPalPurchaseUnitFactory, $this->router))
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()
        ;

        self::assertSame('CAPTURE', $payPalOrder['intent']);
        self::assertSame('REFERENCE_ID', $payPalOrder['purchase_units'][0]['reference_id']);
    }

    public function test_it_sends_the_same_return_and_cancel_url_on_orders_addressed_in_the_wallet(): void
    {
        $payPalOrder = $this->factory->create($this->payment, 'REFERENCE_ID')->toArray();

        self::assertSame([
            'shipping_preference' => 'GET_FROM_FILE',
            'user_action' => 'PAY_NOW',
            'return_url' => 'https://shop.example.com/checkout/complete',
            'cancel_url' => 'https://shop.example.com/checkout/complete',
            'order_update_callback_config' => [
                'callback_events' => ['SHIPPING_ADDRESS'],
                'callback_url' => 'https://shop.example.com/pay-pal-order-shipping-callback',
            ],
        ], $payPalOrder['payment_source']['paypal']['experience_context']);
    }

    public function test_it_does_not_select_a_payment_source_on_orders_that_already_carry_an_address(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $payPalOrder = $this->factory->create($payment, 'REFERENCE_ID')->toArray();

        self::assertArrayNotHasKey('payment_source', $payPalOrder);
        self::assertSame('SET_PROVIDED_ADDRESS', $payPalOrder['application_context']['shipping_preference']);
    }

    public function test_it_does_not_declare_a_shipping_callback_paypal_could_not_reach(): void
    {
        $this->shopScheme = 'http';

        $experienceContext = $this->factory
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()['payment_source']['paypal']['experience_context']
        ;

        self::assertArrayNotHasKey('order_update_callback_config', $experienceContext);
        self::assertSame('http://shop.example.com/checkout/complete', $experienceContext['return_url']);
    }

    public function test_it_sends_no_urls_at_all_without_a_router(): void
    {
        $payPalOrder = (new PayPalOrderFactory($this->payPalPurchaseUnitFactory))
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()
        ;

        self::assertSame([
            'shipping_preference' => 'GET_FROM_FILE',
            'user_action' => 'PAY_NOW',
        ], $payPalOrder['payment_source']['paypal']['experience_context']);
    }

    private function purchaseUnit(): PayPalPurchaseUnit
    {
        return new PayPalPurchaseUnit(
            'REFERENCE_ID',
            'REFERENCE-NUMBER',
            'PLN',
            10000,
            1000,
            90.00,
            0.00,
            0,
            'merchant-id',
            [],
            true,
        );
    }

    private function routeUrl(string $route): string
    {
        $path = match ($route) {
            'sylius_shop_checkout_complete' => '/checkout/complete',
            'sylius_paypal_shop_order_shipping_callback' => '/pay-pal-order-shipping-callback',
        };

        return $this->shopScheme . '://shop.example.com' . $path;
    }
}
