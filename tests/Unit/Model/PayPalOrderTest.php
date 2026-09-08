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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;

final class PayPalOrderTest extends TestCase
{
    private OrderInterface&MockObject $order;

    private PayPalPurchaseUnit&MockObject $payPalPurchaseUnit;

    private PayPalOrder $payPalOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = $this->createMock(OrderInterface::class);
        $this->payPalPurchaseUnit = $this->createMock(PayPalPurchaseUnit::class);
        $this->payPalOrder = new PayPalOrder($this->order, $this->payPalPurchaseUnit, 'CAPTURE');
    }

    #[Test]
    public function it_returns_full_paypal_order_data(): void
    {
        $shippingAddress = $this->createMock(AddressInterface::class);

        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($shippingAddress);

        $payPalOrder = new PayPalOrder(
            $this->order,
            $this->payPalPurchaseUnit,
            'CAPTURE',
            'BRAND_NAME',
            'en-US',
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/checkout/complete',
        );

        $this->payPalPurchaseUnit->method('toArray')->willReturn([
            'reference_id' => 'REFERENCE_ID',
            'invoice_id' => 'INVOICE_ID',
            'items' => [
                ['test_item'],
            ],
        ]);

        self::assertEquals([
            'intent' => 'CAPTURE',
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => 'BRAND_NAME',
                        'locale' => 'en-US',
                        'shipping_preference' => 'SET_PROVIDED_ADDRESS',
                        'contact_preference' => 'RETAIN_CONTACT_INFO',
                        'user_action' => 'PAY_NOW',
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'return_url' => 'https://shop.example.com/checkout/complete',
                        'cancel_url' => 'https://shop.example.com/checkout/complete',
                        'app_switch_preference' => [
                            'launch_paypal_app' => true,
                        ],
                    ],
                ],
            ],
            'purchase_units' => [
                [
                    'reference_id' => 'REFERENCE_ID',
                    'invoice_id' => 'INVOICE_ID',
                    'items' => [
                        ['test_item'],
                    ],
                ],
            ],
        ], $payPalOrder->toArray());
    }

    #[Test]
    public function it_returns_paypal_order_data_without_shipping_address(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $this->payPalPurchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);

        self::assertEquals([
            'intent' => 'CAPTURE',
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'shipping_preference' => 'GET_FROM_FILE',
                        'contact_preference' => 'UPDATE_CONTACT_INFO',
                        'user_action' => 'PAY_NOW',
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'app_switch_preference' => [
                            'launch_paypal_app' => true,
                        ],
                    ],
                ],
            ],
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
        ], $this->payPalOrder->toArray());
    }

    #[Test]
    public function it_returns_paypal_order_data_if_shipping_is_not_required(): void
    {
        $this->order->method('isShippingRequired')->willReturn(false);
        $this->order->method('getShippingAddress')->willReturn(null);

        $this->payPalPurchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);

        self::assertEquals([
            'intent' => 'CAPTURE',
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'shipping_preference' => 'NO_SHIPPING',
                        'contact_preference' => 'UPDATE_CONTACT_INFO',
                        'user_action' => 'PAY_NOW',
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'app_switch_preference' => [
                            'launch_paypal_app' => true,
                        ],
                    ],
                ],
            ],
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
        ], $this->payPalOrder->toArray());
    }

    #[Test]
    public function it_enriches_the_experience_context_with_the_brand_name_and_locale(): void
    {
        $payPalOrder = new PayPalOrder($this->order, $this->payPalPurchaseUnit, 'CAPTURE', 'BRAND_NAME', 'en-US');

        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $experienceContext = $payPalOrder->toArray()['payment_source']['paypal']['experience_context'];

        self::assertSame('BRAND_NAME', $experienceContext['brand_name']);
        self::assertSame('en-US', $experienceContext['locale']);
    }

    #[Test]
    public function it_omits_the_enriched_fields_it_was_not_given(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $experienceContext = $this->payPalOrder->toArray()['payment_source']['paypal']['experience_context'];

        self::assertArrayNotHasKey('brand_name', $experienceContext);
        self::assertArrayNotHasKey('locale', $experienceContext);
        self::assertArrayNotHasKey('return_url', $experienceContext);
        self::assertArrayNotHasKey('cancel_url', $experienceContext);
    }

    #[Test]
    public function it_passes_the_return_and_cancel_urls_to_the_experience_context(): void
    {
        $payPalOrder = new PayPalOrder(
            $this->order,
            $this->payPalPurchaseUnit,
            'CAPTURE',
            null,
            null,
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/checkout/complete',
        );

        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $experienceContext = $payPalOrder->toArray()['payment_source']['paypal']['experience_context'];

        self::assertSame('https://shop.example.com/checkout/complete', $experienceContext['return_url']);
        self::assertSame('https://shop.example.com/checkout/complete', $experienceContext['cancel_url']);
    }

    #[Test]
    public function it_declares_the_shipping_callback_on_orders_addressed_in_the_wallet(): void
    {
        $payPalOrder = new PayPalOrder(
            $this->order,
            $this->payPalPurchaseUnit,
            'CAPTURE',
            shippingCallbackUrl: 'https://shop.example.com/paypal/order-shipping-callback',
        );

        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $result = $payPalOrder->toArray();

        self::assertSame([
            'callback_events' => ['SHIPPING_ADDRESS'],
            'callback_url' => 'https://shop.example.com/paypal/order-shipping-callback',
        ], $result['payment_source']['paypal']['experience_context']['order_update_callback_config']);
    }

    #[Test]
    public function it_declares_no_shipping_callback_when_it_was_not_given_one(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $result = $this->payPalOrder->toArray();

        self::assertArrayNotHasKey('order_update_callback_config', $result['payment_source']['paypal']['experience_context']);
    }

    #[Test]
    public function it_always_sends_the_experience_context_and_never_the_application_context(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $result = $this->payPalOrder->toArray();

        self::assertArrayHasKey('payment_source', $result);
        self::assertArrayNotHasKey('application_context', $result);
    }
}
