<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Model;

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;

final class PayPalOrderSpec extends ObjectBehavior
{
    function let(OrderInterface $order, PayPalPurchaseUnit $payPalPurchaseUnit): void
    {
        $this->beConstructedWith($order, $payPalPurchaseUnit, 'CAPTURE');
    }

    public function it_returns_full_paypal_order_data(
        OrderInterface $order,
        PayPalPurchaseUnit $payPalPurchaseUnit,
        AddressInterface $shippingAddress
    ): void {
        $order->isShippingRequired()->willReturn(true);
        $order->getShippingAddress()->willReturn($shippingAddress);

        $payPalPurchaseUnit->toArray()->willReturn(
            [
                'reference_id' => 'REFERENCE_ID',
                'invoice_number' => 'INVOICE_NUMBER',
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
                    ['test_item']
                ],
                'shipping' => [
                    'name' => [
                        'full_name' => 'Gandalf The Grey'
                    ],
                    'address' => [
                        'address_line_1' => 'Hobbit St. 123',
                        'admin_area_2' => 'Minas Tirith',
                        'postal_code' => '000',
                        'country_code' => 'US'
                    ]
                ],
            ],
        );

        $this->toArray()->shouldReturn(
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'REFERENCE_ID',
                        'invoice_number' => 'INVOICE_NUMBER',
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
                            ['test_item']
                        ],
                        'shipping' => [
                            'name' => [
                                'full_name' => 'Gandalf The Grey'
                            ],
                            'address' => [
                                'address_line_1' => 'Hobbit St. 123',
                                'admin_area_2' => 'Minas Tirith',
                                'postal_code' => '000',
                                'country_code' => 'US'
                            ]
                        ],
                    ],
                ],
                'application_context' => [
                    'shipping_preference' => 'SET_PROVIDED_ADDRESS'
                ]
            ]
        );
    }

    public function it_returns_paypal_order_data_without_shipping_address(
        OrderInterface $order,
        PayPalPurchaseUnit $payPalPurchaseUnit
    ): void {
        $order->isShippingRequired()->willReturn(true);
        $order->getShippingAddress()->willReturn(null);

        $payPalPurchaseUnit->toArray()->willReturn(
            [
                'reference_id' => 'REFERENCE_ID',
                'invoice_number' => 'INVOICE_NUMBER',
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
                    ['test_item']
                ],
            ],
        );

        $this->toArray()->shouldReturn(
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'REFERENCE_ID',
                        'invoice_number' => 'INVOICE_NUMBER',
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
                            ['test_item']
                        ],
                    ],
                ],
                'application_context' => [
                    'shipping_preference' => 'GET_FROM_FILE'
                ]
            ]
        );
    }

    public function it_returns_paypal_order_data_if_shipping_is_not_required(
        OrderInterface $order,
        PayPalPurchaseUnit $payPalPurchaseUnit
    ): void {
        $order->isShippingRequired()->willReturn(false);
        $order->getShippingAddress()->shouldNotBeCalled();

        $payPalPurchaseUnit->toArray()->willReturn(
            [
                'reference_id' => 'REFERENCE_ID',
                'invoice_number' => 'INVOICE_NUMBER',
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
                    ['test_item']
                ],
            ],
        );

        $this->toArray()->shouldReturn(
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'REFERENCE_ID',
                        'invoice_number' => 'INVOICE_NUMBER',
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
                            ['test_item']
                        ],
                    ],
                ],
                'application_context' => [
                    'shipping_preference' => 'NO_SHIPPING'
                ]
            ]
        );
    }
}
