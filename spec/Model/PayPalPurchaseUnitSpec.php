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

final class PayPalPurchaseUnitSpec extends ObjectBehavior
{
    function let(AddressInterface $shippingAddress): void
    {
        $this->beConstructedWith(
            'REFERENCE_ID',
            'INVOICE_NUMBER',
            'CURRENCY_CODE',
            10000,
            1000,
            80,
            10,
            0,
            'MERCHANT_ID',
            [['test_item']],
            true,
            $shippingAddress,
            'DESCRIPTION'
        );
    }

    function it_returns_proper_paypal_purchase_unit(AddressInterface $shippingAddress): void
    {
        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');

        $this->toArray()->shouldReturn(
            [
                'reference_id' => 'REFERENCE_ID',
                'invoice_number' => 'INVOICE_NUMBER',
                'amount' => [
                    'currency_code' => 'CURRENCY_CODE',
                    'value' => '100.00',
                    'breakdown' => [
                        'shipping' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '10.00',
                        ],
                        'item_total' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '80.00',
                        ],
                        'tax_total' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '10.00',
                        ],
                        'discount' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '0.00',
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
    }

    function it_returns_proper_paypal_purchase_unit_if_shipping_is_not_required(AddressInterface $shippingAddress): void
    {
        $this->beConstructedWith(
            'REFERENCE_ID',
            'INVOICE_NUMBER',
            'CURRENCY_CODE',
            10000,
            1000,
            80,
            10,
            0,
            'MERCHANT_ID',
            [['test_item']],
            false,
            $shippingAddress
        );

        $this->toArray()->shouldReturn(
            [
                'reference_id' => 'REFERENCE_ID',
                'invoice_number' => 'INVOICE_NUMBER',
                'amount' => [
                    'currency_code' => 'CURRENCY_CODE',
                    'value' => '100.00',
                    'breakdown' => [
                        'shipping' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '10.00',
                        ],
                        'item_total' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '80.00',
                        ],
                        'tax_total' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '10.00',
                        ],
                        'discount' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '0.00',
                        ],
                    ],
                ],
                'payee' => [
                    'merchant_id' => 'MERCHANT_ID',
                ],
                'soft_descriptor' => 'Sylius PayPal Payment',
                'items' => [
                    ['test_item']
                ],
            ],
        );
    }

    function it_returns_proper_paypal_purchase_unit_if_shipping_is_not_set(): void
    {
        $this->beConstructedWith(
            'REFERENCE_ID',
            'INVOICE_NUMBER',
            'CURRENCY_CODE',
            10000,
            1000,
            80,
            10,
            0,
            'MERCHANT_ID',
            [['test_item']],
            false,
            null
        );

        $this->toArray()->shouldReturn(
            [
                'reference_id' => 'REFERENCE_ID',
                'invoice_number' => 'INVOICE_NUMBER',
                'amount' => [
                    'currency_code' => 'CURRENCY_CODE',
                    'value' => '100.00',
                    'breakdown' => [
                        'shipping' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '10.00',
                        ],
                        'item_total' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '80.00',
                        ],
                        'tax_total' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '10.00',
                        ],
                        'discount' => [
                            'currency_code' => 'CURRENCY_CODE',
                            'value' => '0.00',
                        ],
                    ],
                ],
                'payee' => [
                    'merchant_id' => 'MERCHANT_ID',
                ],
                'soft_descriptor' => 'Sylius PayPal Payment',
                'items' => [
                    ['test_item']
                ],
            ],
        );
    }
}
