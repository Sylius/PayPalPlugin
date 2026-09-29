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
use Sylius\PayPalPlugin\Model\PayPalShippingOption;
use Sylius\PayPalPlugin\Model\PayPalShippingOptions;

final class PayPalShippingCallbackResponseFactoryTest extends TestCase
{
    private const PURCHASE_UNIT = [
        'reference_id' => 'REFERENCE_ID',
        'amount' => [
            'currency_code' => 'USD',
            'value' => '100.00',
            'breakdown' => [
                'item_total' => ['currency_code' => 'USD', 'value' => '100.00'],
                'tax_total' => ['currency_code' => 'USD', 'value' => '0.00'],
                'shipping' => ['currency_code' => 'USD', 'value' => '0.00'],
            ],
        ],
    ];

    private const AMOUNT = [
        'currency_code' => 'USD',
        'value' => '132.50',
        'breakdown' => [
            'shipping' => ['currency_code' => 'USD', 'value' => '25.50'],
            'item_total' => ['currency_code' => 'USD', 'value' => '100.00'],
            'tax_total' => ['currency_code' => 'USD', 'value' => '7.00'],
            'discount' => ['currency_code' => 'USD', 'value' => '0.00'],
            'shipping_discount' => ['currency_code' => 'USD', 'value' => '0.00'],
        ],
    ];

    private PayPalShippingCallbackResponseFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PayPalShippingCallbackResponseFactory();
    }

    private static function shippingOptions(): PayPalShippingOptions
    {
        return new PayPalShippingOptions(
            new PayPalShippingOption('ups', 'UPS', 'USD', 1000),
            new PayPalShippingOption('dhl', 'DHL', 'USD', 2550, true),
        );
    }

    public function test_it_implements_paypal_shipping_callback_response_factory_interface(): void
    {
        self::assertInstanceOf(PayPalShippingCallbackResponseFactoryInterface::class, $this->factory);
    }

    public function test_it_answers_with_the_options_and_the_amount_recalculated_for_the_address(): void
    {
        $response = $this->factory->create('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, self::AMOUNT, self::shippingOptions());

        self::assertSame([
            'id' => 'PAYPAL_ORDER_ID',
            'purchase_units' => [[
                'reference_id' => 'REFERENCE_ID',
                'amount' => self::AMOUNT,
                'shipping_options' => [
                    ['id' => 'ups', 'amount' => ['currency_code' => 'USD', 'value' => '10.00'], 'type' => 'SHIPPING', 'label' => 'UPS', 'selected' => false],
                    ['id' => 'dhl', 'amount' => ['currency_code' => 'USD', 'value' => '25.50'], 'type' => 'SHIPPING', 'label' => 'DHL', 'selected' => true],
                ],
            ]],
        ], $response);
    }

    public function test_it_moves_the_tax_on_shipping_from_the_shipping_cost_to_the_tax_total(): void
    {
        $amount = self::AMOUNT;
        $amount['value'] = '134.50';
        $amount['breakdown']['shipping']['value'] = '27.50';

        $breakdown = $this->factory
            ->create('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, $amount, self::shippingOptions())['purchase_units'][0]['amount']['breakdown']
        ;

        self::assertSame('25.50', $breakdown['shipping']['value']);
        self::assertSame('9.00', $breakdown['tax_total']['value']);
    }

    public function test_it_keeps_the_total_equal_to_the_sum_of_the_breakdown(): void
    {
        $amount = ['currency_code' => 'USD', 'value' => '118.08', 'breakdown' => [
            'item_total' => ['currency_code' => 'USD', 'value' => '90.00'],
            'tax_total' => ['currency_code' => 'USD', 'value' => '0.00'],
            'shipping' => ['currency_code' => 'USD', 'value' => '25.50'],
            'handling' => ['currency_code' => 'USD', 'value' => '1.50'],
            'insurance' => ['currency_code' => 'USD', 'value' => '2.25'],
            'discount' => ['currency_code' => 'USD', 'value' => '5.00'],
            'shipping_discount' => ['currency_code' => 'USD', 'value' => '3.30'],
        ]];

        $amount = $this->factory
            ->create('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, $amount, self::shippingOptions())['purchase_units'][0]['amount']
        ;

        // 118.08 - (90.00 + 25.50 + 1.50 + 2.25 - 5.00 - 3.30)
        self::assertSame('7.13', $amount['breakdown']['tax_total']['value']);
        self::assertSame('118.08', $amount['value']);
    }

    public function test_it_leaves_an_amount_without_a_breakdown_alone(): void
    {
        $amount = ['currency_code' => 'USD', 'value' => '100.00'];

        $response = $this->factory->create('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, $amount, self::shippingOptions());

        self::assertSame($amount, $response['purchase_units'][0]['amount']);
    }

    public function test_it_leaves_the_amount_alone_when_no_option_is_selected(): void
    {
        $options = new PayPalShippingOptions(new PayPalShippingOption('ups', 'UPS', 'USD', 1000));

        $amount = $this->factory
            ->create('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, self::AMOUNT, $options)['purchase_units'][0]['amount']
        ;

        self::assertSame(self::AMOUNT, $amount);
    }

    public function test_it_drops_the_purchase_unit_fields_paypal_does_not_read_back(): void
    {
        $purchaseUnit = self::PURCHASE_UNIT + [
            'payee' => ['merchant_id' => 'MERCHANT_ID'],
            'invoice_id' => 'INVOICE_ID',
            'soft_descriptor' => 'Sylius PayPal Payment',
        ];

        $response = $this->factory->create('PAYPAL_ORDER_ID', $purchaseUnit, self::AMOUNT, self::shippingOptions());

        self::assertSame(
            ['reference_id', 'amount', 'shipping_options'],
            array_keys($response['purchase_units'][0]),
        );
    }

    public function test_it_omits_a_reference_id_the_request_did_not_carry(): void
    {
        $response = $this->factory->create('PAYPAL_ORDER_ID', ['amount' => []], self::AMOUNT, self::shippingOptions());

        self::assertSame(['amount', 'shipping_options'], array_keys($response['purchase_units'][0]));
    }
}
