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
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;

final class PayPalPurchaseUnitTest extends TestCase
{
    private AddressInterface&MockObject $shippingAddress;

    private PayPalPurchaseUnit $payPalPurchaseUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shippingAddress = $this->createMock(AddressInterface::class);
        $this->payPalPurchaseUnit = new PayPalPurchaseUnit(
            'REFERENCE_ID',
            'INVOICE_ID',
            'CURRENCY_CODE',
            10000,
            1000,
            80,
            10,
            0,
            'MERCHANT_ID',
            [['test_item']],
            true,
            $this->shippingAddress,
            'DESCRIPTION',
        );
    }

    #[Test]
    public function it_returns_proper_paypal_purchase_unit(): void
    {
        $this->shippingAddress->method('getFullName')->willReturn('Gandalf The Grey');
        $this->shippingAddress->method('getStreet')->willReturn('Hobbit St. 123');
        $this->shippingAddress->method('getCity')->willReturn('Minas Tirith');
        $this->shippingAddress->method('getPostcode')->willReturn('000');
        $this->shippingAddress->method('getCountryCode')->willReturn('US');

        $result = $this->payPalPurchaseUnit->toArray();

        self::assertEquals([
            'reference_id' => 'REFERENCE_ID',
            'invoice_id' => 'INVOICE_ID',
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
                    'shipping_discount' => [
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
        ], $result);
    }

    #[Test]
    public function it_returns_proper_paypal_purchase_unit_if_shipping_is_not_required(): void
    {
        $payPalPurchaseUnit = new PayPalPurchaseUnit(
            'REFERENCE_ID',
            'INVOICE_ID',
            'CURRENCY_CODE',
            10000,
            1000,
            80,
            10,
            0,
            'MERCHANT_ID',
            [['test_item']],
            false,
            $this->shippingAddress,
        );

        $result = $payPalPurchaseUnit->toArray();

        self::assertEquals([
            'reference_id' => 'REFERENCE_ID',
            'invoice_id' => 'INVOICE_ID',
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
                    'shipping_discount' => [
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
                ['test_item'],
            ],
        ], $result);
    }

    #[Test]
    public function it_includes_custom_id_when_provided(): void
    {
        $payPalPurchaseUnit = new PayPalPurchaseUnit(
            'REFERENCE_ID',
            'INVOICE_ID',
            'CURRENCY_CODE',
            10000,
            1000,
            80,
            10,
            0,
            'MERCHANT_ID',
            [['test_item']],
            false,
            null,
            shippingDiscountValue: 0,
            customId: 'CUSTOM_ID',
        );

        $result = $payPalPurchaseUnit->toArray();

        self::assertSame('CUSTOM_ID', $result['custom_id']);
    }

    #[Test]
    public function it_returns_proper_paypal_purchase_unit_if_shipping_is_not_set(): void
    {
        $payPalPurchaseUnit = new PayPalPurchaseUnit(
            'REFERENCE_ID',
            'INVOICE_ID',
            'CURRENCY_CODE',
            10000,
            1000,
            80,
            10,
            0,
            'MERCHANT_ID',
            [['test_item']],
            false,
            null,
        );

        $result = $payPalPurchaseUnit->toArray();

        self::assertEquals([
            'reference_id' => 'REFERENCE_ID',
            'invoice_id' => 'INVOICE_ID',
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
                    'shipping_discount' => [
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
                ['test_item'],
            ],
        ], $result);
    }
}
