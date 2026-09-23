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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProvider;

final class CarrierProviderTest extends TestCase
{
    #[Test]
    public function it_provides_the_configured_carrier_codes(): void
    {
        $carrierProvider = new CarrierProvider(['FEDEX', 'UPS']);

        self::assertSame(['FEDEX', 'UPS', 'OTHER'], $carrierProvider->getCarrierCodes());
    }

    #[Test]
    public function it_always_provides_the_other_fallback_carrier(): void
    {
        $carrierProvider = new CarrierProvider([]);

        self::assertSame(['OTHER'], $carrierProvider->getCarrierCodes());
    }

    #[Test]
    public function it_does_not_duplicate_the_other_carrier_when_it_is_configured(): void
    {
        $carrierProvider = new CarrierProvider(['OTHER', 'FEDEX', 'FEDEX']);

        self::assertSame(['OTHER', 'FEDEX'], $carrierProvider->getCarrierCodes());
    }

    #[Test]
    public function it_recognises_the_other_fallback_carrier(): void
    {
        $carrierProvider = new CarrierProvider(['FEDEX']);

        self::assertTrue($carrierProvider->isOther('OTHER'));
        self::assertFalse($carrierProvider->isOther('FEDEX'));
    }
}
