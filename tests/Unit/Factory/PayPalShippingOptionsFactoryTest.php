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
use Sylius\PayPalPlugin\Factory\PayPalShippingOptionsFactory;
use Sylius\PayPalPlugin\Factory\PayPalShippingOptionsFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalShippingOption;

final class PayPalShippingOptionsFactoryTest extends TestCase
{
    private PayPalShippingOptionsFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PayPalShippingOptionsFactory();
    }

    public function test_it_implements_paypal_shipping_options_factory_interface(): void
    {
        self::assertInstanceOf(PayPalShippingOptionsFactoryInterface::class, $this->factory);
    }

    public function test_it_creates_an_empty_collection_from_no_options(): void
    {
        $options = $this->factory->create([]);

        self::assertTrue($options->isEmpty());
        self::assertNull($options->selected());
    }

    public function test_it_leaves_the_selection_alone_when_an_option_is_already_selected(): void
    {
        $options = $this->factory->create([
            new PayPalShippingOption('ups', 'UPS', 'USD', 1000),
            new PayPalShippingOption('dhl', 'DHL', 'USD', 2550, true),
        ]);

        $selected = $options->selected();
        self::assertNotNull($selected);
        self::assertSame('dhl', $selected->id());
        self::assertSame([false, true], self::selectionOf($options->toArray()));
    }

    public function test_it_selects_the_first_option_when_none_is_selected(): void
    {
        $options = $this->factory->create([
            new PayPalShippingOption('dhl', 'DHL', 'USD', 2550),
            new PayPalShippingOption('ups', 'UPS', 'USD', 1000),
        ]);

        $selected = $options->selected();
        self::assertNotNull($selected);
        self::assertSame('dhl', $selected->id());
        self::assertSame([true, false], self::selectionOf($options->toArray()));
    }

    public function test_it_keeps_the_order_it_was_given(): void
    {
        $options = $this->factory->create([
            new PayPalShippingOption('dhl', 'DHL', 'USD', 2550),
            new PayPalShippingOption('ups', 'UPS', 'USD', 1000),
        ]);

        self::assertSame(['dhl', 'ups'], array_column($options->toArray(), 'id'));
    }

    /**
     * @param array<int, array<string, mixed>> $options
     *
     * @return array<int, bool>
     */
    private static function selectionOf(array $options): array
    {
        return array_column($options, 'selected');
    }
}
