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

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Factory\PayPalShippingCallbackResponseFactory;
use Sylius\PayPalPlugin\Factory\PayPalShippingCallbackResponseFactoryInterface;

final class PayPalShippingCallbackResponseFactoryTest extends TestCase
{
    private const PURCHASE_UNIT = [
        'reference_id' => 'REFERENCE_ID',
        'amount' => [
            'currency_code' => 'USD',
            'value' => '100.00',
            'breakdown' => [
                'item_total' => ['currency_code' => 'USD', 'value' => '90.00'],
                'tax_total' => ['currency_code' => 'USD', 'value' => '10.00'],
                'shipping' => ['currency_code' => 'USD', 'value' => '0.00'],
            ],
        ],
    ];

    private const SHIPPING_OPTIONS = [
        ['id' => 'ups', 'amount' => ['currency_code' => 'USD', 'value' => '10.00'], 'type' => 'SHIPPING', 'label' => 'UPS', 'selected' => false],
        ['id' => 'dhl', 'amount' => ['currency_code' => 'USD', 'value' => '25.50'], 'type' => 'SHIPPING', 'label' => 'DHL', 'selected' => true],
    ];

    private PayPalShippingCallbackResponseFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PayPalShippingCallbackResponseFactory();
    }

    public function test_it_implements_paypal_shipping_callback_response_factory_interface(): void
    {
        self::assertInstanceOf(PayPalShippingCallbackResponseFactoryInterface::class, $this->factory);
    }

    public function test_it_answers_with_the_options_and_the_cost_of_the_selected_one(): void
    {
        $response = $this->factory->create('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, self::SHIPPING_OPTIONS);

        self::assertSame([
            'id' => 'PAYPAL_ORDER_ID',
            'purchase_units' => [[
                'reference_id' => 'REFERENCE_ID',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '125.50',
                    'breakdown' => [
                        'item_total' => ['currency_code' => 'USD', 'value' => '90.00'],
                        'tax_total' => ['currency_code' => 'USD', 'value' => '10.00'],
                        'shipping' => ['currency_code' => 'USD', 'value' => '25.50'],
                    ],
                ],
                'shipping_options' => self::SHIPPING_OPTIONS,
            ]],
        ], $response);
    }

    public function test_it_keeps_the_total_equal_to_the_sum_of_the_breakdown(): void
    {
        $purchaseUnit = ['amount' => ['currency_code' => 'USD', 'value' => '0.00', 'breakdown' => [
            'item_total' => ['currency_code' => 'USD', 'value' => '90.00'],
            'tax_total' => ['currency_code' => 'USD', 'value' => '7.13'],
            'handling' => ['currency_code' => 'USD', 'value' => '1.50'],
            'insurance' => ['currency_code' => 'USD', 'value' => '2.25'],
            'discount' => ['currency_code' => 'USD', 'value' => '5.00'],
            'shipping_discount' => ['currency_code' => 'USD', 'value' => '3.30'],
        ]]];

        $amount = $this->factory
            ->create('PAYPAL_ORDER_ID', $purchaseUnit, self::SHIPPING_OPTIONS)['purchase_units'][0]['amount']
        ;

        // 90.00 + 7.13 + 25.50 + 1.50 + 2.25 - 5.00 - 3.30
        self::assertSame('118.08', $amount['value']);
    }

    public function test_it_leaves_an_amount_without_a_breakdown_alone(): void
    {
        $amount = ['currency_code' => 'USD', 'value' => '100.00'];

        $response = $this->factory->create('PAYPAL_ORDER_ID', ['amount' => $amount], self::SHIPPING_OPTIONS);

        self::assertSame($amount, $response['purchase_units'][0]['amount']);
    }

    public function test_it_leaves_the_amount_alone_when_no_option_is_selected(): void
    {
        $options = [
            ['id' => 'ups', 'amount' => ['currency_code' => 'USD', 'value' => '10.00'], 'selected' => false],
        ];

        $amount = $this->factory
            ->create('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, $options)['purchase_units'][0]['amount']
        ;

        self::assertSame(self::PURCHASE_UNIT['amount'], $amount);
    }

    public function test_it_drops_the_purchase_unit_fields_paypal_does_not_read_back(): void
    {
        $purchaseUnit = self::PURCHASE_UNIT + [
            'payee' => ['merchant_id' => 'MERCHANT_ID'],
            'invoice_id' => 'INVOICE_ID',
            'soft_descriptor' => 'Sylius PayPal Payment',
        ];

        $response = $this->factory->create('PAYPAL_ORDER_ID', $purchaseUnit, self::SHIPPING_OPTIONS);

        self::assertSame(
            ['reference_id', 'amount', 'shipping_options'],
            array_keys($response['purchase_units'][0]),
        );
    }

    public function test_it_omits_a_reference_id_the_request_did_not_carry(): void
    {
        $response = $this->factory->create('PAYPAL_ORDER_ID', ['amount' => []], self::SHIPPING_OPTIONS);

        self::assertSame(['amount', 'shipping_options'], array_keys($response['purchase_units'][0]));
    }
}
