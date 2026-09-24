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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;

final class ShipmentTrackingDataTest extends TestCase
{
    #[Test]
    public function it_normalises_blank_values_to_null(): void
    {
        $shipmentTrackingData = new ShipmentTrackingData('', '   ', '');

        self::assertNull($shipmentTrackingData->getCarrier());
        self::assertNull($shipmentTrackingData->getCarrierNameOther());
        self::assertNull($shipmentTrackingData->getTrackingNumber());
    }

    #[Test]
    public function it_trims_the_submitted_values(): void
    {
        $shipmentTrackingData = new ShipmentTrackingData();
        $shipmentTrackingData->setCarrier(' FEDEX ');
        $shipmentTrackingData->setCarrierNameOther(' Pigeon Post ');
        $shipmentTrackingData->setTrackingNumber(' TRACK1 ');

        self::assertSame('FEDEX', $shipmentTrackingData->getCarrier());
        self::assertSame('Pigeon Post', $shipmentTrackingData->getCarrierNameOther());
        self::assertSame('TRACK1', $shipmentTrackingData->getTrackingNumber());
    }
}
