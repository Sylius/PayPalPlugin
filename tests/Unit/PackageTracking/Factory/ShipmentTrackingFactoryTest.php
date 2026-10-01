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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\PackageTracking\Factory\ShipmentTrackingFactory;
use Sylius\PayPalPlugin\PackageTracking\Factory\ShipmentTrackingFactoryInterface;

final class ShipmentTrackingFactoryTest extends TestCase
{
    private ShipmentTrackingFactory $shipmentTrackingFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shipmentTrackingFactory = new ShipmentTrackingFactory();
    }

    #[Test]
    public function it_implements_shipment_tracking_factory_interface(): void
    {
        self::assertInstanceOf(ShipmentTrackingFactoryInterface::class, $this->shipmentTrackingFactory);
    }

    #[Test]
    public function it_creates_a_pending_tracking_for_the_given_shipment(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);

        $tracking = $this->shipmentTrackingFactory->createForShipment($shipment);

        self::assertSame($shipment, $tracking->getShipment());
        self::assertSame(ShipmentTrackingInterface::STATE_PENDING, $tracking->getState());
        self::assertSame(0, $tracking->getAttempts());
    }
}
