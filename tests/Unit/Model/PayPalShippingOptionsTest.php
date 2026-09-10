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
use Sylius\PayPalPlugin\Model\PayPalShippingOptions;

final class PayPalShippingOptionsTest extends TestCase
{
    public function test_it_is_empty_without_any_option(): void
    {
        self::assertTrue((new PayPalShippingOptions())->isEmpty());
        self::assertSame([], (new PayPalShippingOptions())->toArray());
    }

    public function test_it_is_not_empty_with_an_option(): void
    {
        $options = new PayPalShippingOptions(new PayPalShippingOption('ups', 'UPS', 'USD', 1000));

        self::assertFalse($options->isEmpty());
    }

    public function test_it_hands_back_the_option_marked_as_selected(): void
    {
        $selected = new PayPalShippingOption('dhl', 'DHL', 'USD', 2550, true);
        $options = new PayPalShippingOptions(new PayPalShippingOption('ups', 'UPS', 'USD', 1000), $selected);

        self::assertSame($selected, $options->selected());
    }

    public function test_it_hands_back_nothing_when_no_option_is_selected(): void
    {
        $options = new PayPalShippingOptions(new PayPalShippingOption('ups', 'UPS', 'USD', 1000));

        self::assertNull($options->selected());
    }

    public function test_it_hands_back_nothing_selected_when_it_is_empty(): void
    {
        self::assertNull((new PayPalShippingOptions())->selected());
    }

    public function test_it_hands_back_the_first_selected_option(): void
    {
        $first = new PayPalShippingOption('ups', 'UPS', 'USD', 1000, true);
        $options = new PayPalShippingOptions($first, new PayPalShippingOption('dhl', 'DHL', 'USD', 2550, true));

        self::assertSame($first, $options->selected());
    }

    public function test_it_shapes_its_options_in_the_order_it_was_given_them(): void
    {
        $options = new PayPalShippingOptions(
            new PayPalShippingOption('ups', 'UPS', 'USD', 1000),
            new PayPalShippingOption('dhl', 'DHL', 'USD', 2550, true),
        );

        self::assertSame([
            ['id' => 'ups', 'amount' => ['currency_code' => 'USD', 'value' => '10.00'], 'type' => 'SHIPPING', 'label' => 'UPS', 'selected' => false],
            ['id' => 'dhl', 'amount' => ['currency_code' => 'USD', 'value' => '25.50'], 'type' => 'SHIPPING', 'label' => 'DHL', 'selected' => true],
        ], $options->toArray());
    }
}
