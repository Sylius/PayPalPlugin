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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\PayPalPlugin\Provider\PayPalRegionProvider;

final class PayPalRegionProviderTest extends TestCase
{
    private AddressInterface&MockObject $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->address = $this->createMock(AddressInterface::class);
    }

    public function test_it_strips_the_country_prefix_from_the_province_code(): void
    {
        $this->address->method('getCountryCode')->willReturn('US');
        $this->address->method('getProvinceCode')->willReturn('US-TX');

        self::assertSame('TX', PayPalRegionProvider::provide($this->address));
    }

    public function test_it_keeps_a_province_code_that_carries_no_country_prefix(): void
    {
        $this->address->method('getCountryCode')->willReturn('US');
        $this->address->method('getProvinceCode')->willReturn('TX');

        self::assertSame('TX', PayPalRegionProvider::provide($this->address));
    }

    public function test_it_keeps_a_province_code_prefixed_with_another_country(): void
    {
        $this->address->method('getCountryCode')->willReturn('US');
        $this->address->method('getProvinceCode')->willReturn('CA-ON');

        self::assertSame('CA-ON', PayPalRegionProvider::provide($this->address));
    }

    public function test_it_falls_back_to_the_province_name_when_there_is_no_province_code(): void
    {
        $this->address->method('getCountryCode')->willReturn('PL');
        $this->address->method('getProvinceName')->willReturn('Mazowieckie');

        self::assertSame('Mazowieckie', PayPalRegionProvider::provide($this->address));
    }

    public function test_it_prefers_the_province_code_over_the_province_name(): void
    {
        $this->address->method('getCountryCode')->willReturn('US');
        $this->address->method('getProvinceCode')->willReturn('US-TX');
        $this->address->method('getProvinceName')->willReturn('Texas');

        self::assertSame('TX', PayPalRegionProvider::provide($this->address));
    }

    public function test_it_provides_nothing_when_the_address_carries_neither(): void
    {
        $this->address->method('getCountryCode')->willReturn('US');

        self::assertNull(PayPalRegionProvider::provide($this->address));
    }

    public function test_it_treats_a_blank_region_as_absent(): void
    {
        $this->address->method('getCountryCode')->willReturn('US');
        $this->address->method('getProvinceCode')->willReturn('   ');
        $this->address->method('getProvinceName')->willReturn('');

        self::assertNull(PayPalRegionProvider::provide($this->address));
    }
}
