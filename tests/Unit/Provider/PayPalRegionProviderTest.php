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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\PayPalPlugin\Provider\PayPalRegionProvider;

final class PayPalRegionProviderTest extends TestCase
{
    #[DataProvider('regionProvider')]
    public function test_it_provides_the_region_pay_pal_expects_for_an_address(
        ?string $countryCode,
        ?string $provinceCode,
        ?string $provinceName,
        ?string $expectedRegion,
    ): void {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn($countryCode);
        $address->method('getProvinceCode')->willReturn($provinceCode);
        $address->method('getProvinceName')->willReturn($provinceName);

        self::assertSame($expectedRegion, PayPalRegionProvider::provide($address));
    }

    /** @return iterable<string, array{?string, ?string, ?string, ?string}> */
    public static function regionProvider(): iterable
    {
        yield 'province code carrying the country prefix' => ['US', 'US-TX', null, 'TX'];
        yield 'province code without a country prefix' => ['US', 'TX', null, 'TX'];
        yield 'province code prefixed with another country' => ['US', 'CA-ON', null, 'CA-ON'];
        yield 'province name only' => ['PL', null, 'Mazowieckie', 'Mazowieckie'];
        yield 'province code over province name' => ['US', 'US-TX', 'Texas', 'TX'];
        yield 'neither code nor name' => ['US', null, null, null];
        yield 'blank code and blank name' => ['US', '   ', '', null];
    }
}
