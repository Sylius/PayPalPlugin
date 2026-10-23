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
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\PayPalItem;

final class PayPalItemTest extends TestCase
{
    #[Test]
    public function it_formats_the_minor_unit_amounts_and_omits_the_optional_fields(): void
    {
        $item = new PayPalItem('PRODUCT_ONE', 2, 2000, 200, 'PLN', 'PHYSICAL_GOODS');

        self::assertSame([
            'name' => 'PRODUCT_ONE',
            'unit_amount' => [
                'value' => '20.00',
                'currency_code' => 'PLN',
            ],
            'quantity' => 2,
            'tax' => [
                'value' => '2.00',
                'currency_code' => 'PLN',
            ],
            'category' => 'PHYSICAL_GOODS',
        ], $item->toArray());
    }

    #[Test]
    public function it_adds_the_optional_fields_when_they_are_given(): void
    {
        $item = new PayPalItem(
            'PRODUCT_ONE',
            1,
            2000,
            0,
            'PLN',
            'DIGITAL_GOODS',
            'SKU_1',
            'A very nice product',
            'http://localhost/products/product-one',
        );

        $result = $item->toArray();

        self::assertSame('SKU_1', $result['sku']);
        self::assertSame('A very nice product', $result['description']);
        self::assertSame('http://localhost/products/product-one', $result['url']);
    }
}
