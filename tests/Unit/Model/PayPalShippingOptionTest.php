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

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\PayPalShippingOption;

final class PayPalShippingOptionTest extends TestCase
{
    public function test_it_shapes_itself_the_way_paypal_reads_shipping_options(): void
    {
        $option = new PayPalShippingOption('ups', 'UPS', 'USD', 1000, true);

        self::assertSame([
            'id' => 'ups',
            'amount' => ['currency_code' => 'USD', 'value' => '10.00'],
            'type' => 'SHIPPING',
            'label' => 'UPS',
            'selected' => true,
        ], $option->toArray());
    }

    public function test_it_is_not_selected_and_ships_rather_than_waits_for_pickup_by_default(): void
    {
        $option = new PayPalShippingOption('ups', 'UPS', 'USD', 1000);

        self::assertFalse($option->isSelected());
        self::assertSame(PayPalShippingOption::TYPE_SHIPPING, $option->type());
    }

    public function test_it_carries_a_pickup_type_when_it_is_given_one(): void
    {
        $option = new PayPalShippingOption('shop', 'Pick up in shop', 'USD', 0, false, PayPalShippingOption::TYPE_PICKUP);

        self::assertSame('PICKUP', $option->toArray()['type']);
    }

    public function test_it_keeps_the_amount_in_minor_units_and_formats_it_only_for_paypal(): void
    {
        $option = new PayPalShippingOption('ups', 'UPS', 'PLN', 1999);

        self::assertSame(1999, $option->amount());
        self::assertSame(['currency_code' => 'PLN', 'value' => '19.99'], $option->amountToArray());
    }

    public function test_it_formats_a_free_option_as_zero(): void
    {
        $option = new PayPalShippingOption('free', 'Free shipping', 'EUR', 0);

        self::assertSame(['currency_code' => 'EUR', 'value' => '0.00'], $option->amountToArray());
    }

    public function test_it_hands_back_a_copy_when_its_selection_changes(): void
    {
        $option = new PayPalShippingOption('ups', 'UPS', 'USD', 1000);

        $selected = $option->withSelected(true);

        self::assertFalse($option->isSelected());
        self::assertTrue($selected->isSelected());
        self::assertSame(
            ['ups', 'UPS', 'USD', 1000],
            [$selected->id(), $selected->label(), $selected->currencyCode(), $selected->amount()],
        );
    }
}
