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

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = $this->createMock(OrderInterface::class);
        $this->payPalPurchaseUnit = $this->createMock(PayPalPurchaseUnit::class);
    }

    #[Test]
    public function it_sends_the_given_experience_context_under_the_paypal_payment_source(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);

        $experienceContext = [
            'locale' => 'en-US',
            'shipping_preference' => PayPalOrder::PAYPAL_ADDRESS,
            'contact_preference' => PayPalOrder::UPDATE_CONTACT_INFO,
            'user_action' => PayPalOrder::USER_ACTION_PAY_NOW,
            'payment_method_preference' => PayPalOrder::PAYMENT_METHOD_PREFERENCE_IMMEDIATE,
            'app_switch_preference' => ['launch_paypal_app' => true],
        ];

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            experienceContext: $experienceContext,
        );

        self::assertEquals([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => $experienceContext,
                ],
            ],
        ], $payPalOrder->toArray());
    }

    #[Test]
    public function it_sends_the_experience_context_when_the_address_is_already_provided(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $this->payPalPurchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);

        $experienceContext = ['shipping_preference' => PayPalOrder::PROVIDED_ADDRESS];

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            experienceContext: $experienceContext,
        );

        $result = $payPalOrder->toArray();

        self::assertArrayNotHasKey('application_context', $result);
        self::assertSame($experienceContext, $result['payment_source']['paypal']['experience_context']);
    }

    #[Test]
    public function it_sends_the_fallback_experience_context_when_shipping_is_not_required(): void
    {
        $this->order->method('isShippingRequired')->willReturn(false);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder($this->order, $this->payPalPurchaseUnit, PayPalOrder::INTENT_CAPTURE);

        $result = $payPalOrder->toArray();

        self::assertArrayNotHasKey('application_context', $result);
        self::assertSame(
            [
                'shipping_preference' => 'NO_SHIPPING',
                'contact_preference' => PayPalOrder::UPDATE_CONTACT_INFO,
                'user_action' => 'PAY_NOW',
                'payment_method_preference' => PayPalOrder::PAYMENT_METHOD_PREFERENCE_IMMEDIATE,
                'app_switch_preference' => ['launch_paypal_app' => true],
            ],
            $result['payment_source']['paypal']['experience_context'],
        );
    }

    #[Test]
    public function it_never_sends_the_legacy_application_context(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            experienceContext: ['shipping_preference' => PayPalOrder::PAYPAL_ADDRESS],
        );

        $result = $payPalOrder->toArray();

        self::assertArrayHasKey('payment_source', $result);
        self::assertArrayNotHasKey('application_context', $result);
    }

    #[Test]
    public function it_builds_the_experience_context_from_the_urls_when_none_is_given(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            $this->order,
            $this->payPalPurchaseUnit,
            PayPalOrder::INTENT_CAPTURE,
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/checkout/complete',
        );

        self::assertSame([
            'shipping_preference' => 'GET_FROM_FILE',
            'contact_preference' => PayPalOrder::UPDATE_CONTACT_INFO,
            'user_action' => 'PAY_NOW',
            'payment_method_preference' => PayPalOrder::PAYMENT_METHOD_PREFERENCE_IMMEDIATE,
            'return_url' => 'https://shop.example.com/checkout/complete',
            'cancel_url' => 'https://shop.example.com/checkout/complete',
            'app_switch_preference' => ['launch_paypal_app' => true],
        ], $payPalOrder->toArray()['payment_source']['paypal']['experience_context']);
    }

    #[Test]
    public function it_declares_the_shipping_callback_in_the_fallback_experience_context(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            shippingCallbackUrl: 'https://shop.example.com/paypal/order-shipping-callback',
        );

        self::assertSame([
            'callback_events' => ['SHIPPING_ADDRESS'],
            'callback_url' => 'https://shop.example.com/paypal/order-shipping-callback',
        ], $payPalOrder->toArray()['payment_source']['paypal']['experience_context']['order_update_callback_config']);
    }

    #[Test]
    public function it_omits_the_shipping_callback_from_the_fallback_when_the_address_is_already_provided(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            shippingCallbackUrl: 'https://shop.example.com/paypal/order-shipping-callback',
        );

        self::assertArrayNotHasKey(
            'order_update_callback_config',
            $payPalOrder->toArray()['payment_source']['paypal']['experience_context'],
        );
    }
}
