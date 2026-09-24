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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTracking;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\PackageTracking\Factory\ShipmentTrackingFactoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManager;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProvider;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;

final class ShipmentTrackingManagerTest extends TestCase
{
    private ShipmentTrackingRepositoryInterface&MockObject $shipmentTrackingRepository;

    private ShipmentTrackingFactoryInterface&MockObject $shipmentTrackingFactory;

    private EntityManagerInterface&MockObject $entityManager;

    private ShipmentTrackingManager $shipmentTrackingManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shipmentTrackingRepository = $this->createMock(ShipmentTrackingRepositoryInterface::class);
        $this->shipmentTrackingFactory = $this->createMock(ShipmentTrackingFactoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->shipmentTrackingManager = new ShipmentTrackingManager(
            $this->shipmentTrackingRepository,
            $this->shipmentTrackingFactory,
            new CarrierProvider(['FEDEX']),
            $this->entityManager,
        );
    }

    #[Test]
    public function it_implements_shipment_tracking_manager_interface(): void
    {
        self::assertInstanceOf(ShipmentTrackingManagerInterface::class, $this->shipmentTrackingManager);
    }

    #[Test]
    public function it_creates_a_tracking_with_the_factory_when_the_shipment_has_none(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $tracking = new ShipmentTracking($shipment);

        $this->shipmentTrackingRepository->method('findOneByShipment')->with($shipment)->willReturn(null);
        $this->shipmentTrackingFactory
            ->expects(self::once())
            ->method('createForShipment')
            ->with($shipment)
            ->willReturn($tracking)
        ;

        $this->entityManager->expects(self::once())->method('persist')->with($tracking);
        $this->entityManager->expects(self::once())->method('flush');

        $this->shipmentTrackingManager->updateCarrier($shipment, 'FEDEX', null);

        self::assertSame('FEDEX', $tracking->getCarrier());
        self::assertNull($tracking->getCarrierNameOther());
    }

    #[Test]
    public function it_reuses_the_existing_tracking_of_the_shipment(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $tracking = new ShipmentTracking($shipment);
        $tracking->markAsFailed('PayPal said no');

        $this->shipmentTrackingRepository->method('findOneByShipment')->with($shipment)->willReturn($tracking);
        $this->shipmentTrackingFactory->expects(self::never())->method('createForShipment');

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->shipmentTrackingManager->updateCarrier($shipment, 'FEDEX', null);

        self::assertSame(ShipmentTrackingInterface::STATE_PENDING, $tracking->getState());
        self::assertSame('FEDEX', $tracking->getCarrier());
    }

    #[Test]
    public function it_keeps_the_other_carrier_name_only_for_the_other_carrier(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $tracking = new ShipmentTracking($shipment);

        $this->shipmentTrackingRepository->method('findOneByShipment')->willReturn($tracking);

        $this->shipmentTrackingManager->updateCarrier($shipment, CarrierProviderInterface::OTHER_CARRIER_CODE, 'Pigeon Post');

        self::assertSame('Pigeon Post', $tracking->getCarrierNameOther());
    }

    #[Test]
    public function it_discards_the_other_carrier_name_for_a_known_carrier(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $tracking = new ShipmentTracking($shipment);

        $this->shipmentTrackingRepository->method('findOneByShipment')->willReturn($tracking);

        $this->shipmentTrackingManager->updateCarrier($shipment, 'FEDEX', 'Pigeon Post');

        self::assertNull($tracking->getCarrierNameOther());
    }
}
