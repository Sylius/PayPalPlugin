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

        $this->payPalPurchaseUnit->method('toArray')->willReturn([
            'reference_id' => 'REFERENCE_ID',
            'invoice_id' => 'INVOICE_ID',
            'amount' => [
                'currency_code' => 'CURRENCY_CODE',
                'value' => 100,
                'breakdown' => [
                    'shipping' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 10,
                    ],
                    'item_total' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 80,
                    ],
                    'tax_total' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 10,
                    ],
                    'discount' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 0,
                    ],
                ],
            ],
            'payee' => [
                'merchant_id' => 'MERCHANT_ID',
            ],
            'soft_descriptor' => 'DESCRIPTION',
            'items' => [
                ['test_item'],
            ],
            'shipping' => [
                'name' => [
                    'full_name' => 'Gandalf The Grey',
                ],
                'address' => [
                    'address_line_1' => 'Hobbit St. 123',
                    'admin_area_2' => 'Minas Tirith',
                    'postal_code' => '000',
                    'country_code' => 'US',
                ],
            ],
        ]);

        $result = $this->payPalOrder->toArray();

        self::assertEquals([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'REFERENCE_ID',
                    'invoice_id' => 'INVOICE_ID',
                    'amount' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 100,
                        'breakdown' => [
                            'shipping' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 10,
                            ],
                            'item_total' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 80,
                            ],
                            'tax_total' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 10,
                            ],
                            'discount' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 0,
                            ],
                        ],
                    ],
                    'payee' => [
                        'merchant_id' => 'MERCHANT_ID',
                    ],
                    'soft_descriptor' => 'DESCRIPTION',
                    'items' => [
                        ['test_item'],
                    ],
                    'shipping' => [
                        'name' => [
                            'full_name' => 'Gandalf The Grey',
                        ],
                        'address' => [
                            'address_line_1' => 'Hobbit St. 123',
                            'admin_area_2' => 'Minas Tirith',
                            'postal_code' => '000',
                            'country_code' => 'US',
                        ],
                    ],
                ],
            ],
            'application_context' => [
                'shipping_preference' => 'SET_PROVIDED_ADDRESS',
                'user_action' => 'PAY_NOW',
            ],
        ], $result);
    }

    #[Test]
    public function it_returns_paypal_order_data_without_shipping_address(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $this->payPalPurchaseUnit->method('toArray')->willReturn([
            'reference_id' => 'REFERENCE_ID',
            'invoice_id' => 'INVOICE_ID',
            'amount' => [
                'currency_code' => 'CURRENCY_CODE',
                'value' => 100,
                'breakdown' => [
                    'shipping' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 10,
                    ],
                    'item_total' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 80,
                    ],
                    'tax_total' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 10,
                    ],
                    'discount' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 0,
                    ],
                ],
            ],
            'payee' => [
                'merchant_id' => 'MERCHANT_ID',
            ],
            'soft_descriptor' => 'DESCRIPTION',
            'items' => [
                ['test_item'],
            ],
        ]);

        $result = $this->payPalOrder->toArray();

        self::assertEquals([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'REFERENCE_ID',
                    'invoice_id' => 'INVOICE_ID',
                    'amount' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 100,
                        'breakdown' => [
                            'shipping' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 10,
                            ],
                            'item_total' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 80,
                            ],
                            'tax_total' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 10,
                            ],
                            'discount' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 0,
                            ],
                        ],
                    ],
                    'payee' => [
                        'merchant_id' => 'MERCHANT_ID',
                    ],
                    'soft_descriptor' => 'DESCRIPTION',
                    'items' => [
                        ['test_item'],
                    ],
                ],
            ],
            'application_context' => [
                'shipping_preference' => 'GET_FROM_FILE',
                'user_action' => 'PAY_NOW',
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'shipping_preference' => 'GET_FROM_FILE',
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ],
        ], $result);
    }

    #[Test]
    public function it_returns_paypal_order_data_if_shipping_is_not_required(): void
    {
        $this->order->method('isShippingRequired')->willReturn(false);

        $this->payPalPurchaseUnit->method('toArray')->willReturn([
            'reference_id' => 'REFERENCE_ID',
            'invoice_id' => 'INVOICE_ID',
            'amount' => [
                'currency_code' => 'CURRENCY_CODE',
                'value' => 100,
                'breakdown' => [
                    'shipping' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 10,
                    ],
                    'item_total' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 80,
                    ],
                    'tax_total' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 10,
                    ],
                    'discount' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 0,
                    ],
                ],
            ],
            'payee' => [
                'merchant_id' => 'MERCHANT_ID',
            ],
            'soft_descriptor' => 'DESCRIPTION',
            'items' => [
                ['test_item'],
            ],
        ]);

        $result = $this->payPalOrder->toArray();

        self::assertEquals([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'REFERENCE_ID',
                    'invoice_id' => 'INVOICE_ID',
                    'amount' => [
                        'currency_code' => 'CURRENCY_CODE',
                        'value' => 100,
                        'breakdown' => [
                            'shipping' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 10,
                            ],
                            'item_total' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 80,
                            ],
                            'tax_total' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 10,
                            ],
                            'discount' => [
                                'currency_code' => 'CURRENCY_CODE',
                                'value' => 0,
                            ],
                        ],
                    ],
                    'payee' => [
                        'merchant_id' => 'MERCHANT_ID',
                    ],
                    'soft_descriptor' => 'DESCRIPTION',
                    'items' => [
                        ['test_item'],
                    ],
                ],
            ],
            'application_context' => [
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ], $result);
    }

    #[Test]
    public function it_selects_the_paypal_payment_source_when_paypal_supplies_the_shipping_address(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $result = $this->payPalOrder->toArray();

        self::assertSame(
            ['shipping_preference' => 'GET_FROM_FILE', 'user_action' => 'PAY_NOW'],
            $result['payment_source']['paypal']['experience_context'],
        );
    }

    #[Test]
    public function it_does_not_select_a_payment_source_when_the_shipping_address_is_already_known(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $result = $this->payPalOrder->toArray();

        self::assertSame(['shipping_preference' => 'SET_PROVIDED_ADDRESS', 'user_action' => 'PAY_NOW'], $result['application_context']);
        self::assertArrayNotHasKey('payment_source', $result);
    }

    #[Test]
    public function it_does_not_select_a_payment_source_when_shipping_is_not_required(): void
    {
        $this->order->method('isShippingRequired')->willReturn(false);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $result = $this->payPalOrder->toArray();

        self::assertSame(['shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW'], $result['application_context']);
        self::assertArrayNotHasKey('payment_source', $result);
    }

    #[Test]
    public function it_passes_the_return_and_cancel_urls_to_the_experience_context(): void
    {
        $payPalOrder = new PayPalOrder(
            $this->order,
            $this->payPalPurchaseUnit,
            'CAPTURE',
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/checkout/complete',
        );

        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $result = $payPalOrder->toArray();

        self::assertSame([
            'shipping_preference' => 'GET_FROM_FILE',
            'user_action' => 'PAY_NOW',
            'return_url' => 'https://shop.example.com/checkout/complete',
            'cancel_url' => 'https://shop.example.com/checkout/complete',
        ], $result['payment_source']['paypal']['experience_context']);
    }

    #[Test]
    public function it_declares_the_shipping_callback_on_orders_addressed_in_the_wallet(): void
    {
        $payPalOrder = new PayPalOrder(
            $this->order,
            $this->payPalPurchaseUnit,
            'CAPTURE',
            shippingCallbackUrl: 'https://shop.example.com/pay-pal-order-shipping-callback',
        );

        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $result = $payPalOrder->toArray();

        self::assertSame([
            'callback_events' => ['SHIPPING_ADDRESS'],
            'callback_url' => 'https://shop.example.com/pay-pal-order-shipping-callback',
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
}
