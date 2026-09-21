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

namespace Sylius\PayPalPlugin\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Factory\ShipmentTrackingFactoryInterface;
use Sylius\PayPalPlugin\Provider\CarrierProviderInterface;
use Sylius\PayPalPlugin\Repository\ShipmentTrackingRepositoryInterface;

final readonly class ShipmentTrackingManager implements ShipmentTrackingManagerInterface
{
    public function __construct(
        private ShipmentTrackingRepositoryInterface $shipmentTrackingRepository,
        private ShipmentTrackingFactoryInterface $shipmentTrackingFactory,
        private CarrierProviderInterface $carrierProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function updateCarrier(ShipmentInterface $shipment, ?string $carrier, ?string $carrierNameOther): void
    {
        $tracking = $this->shipmentTrackingRepository->findOneByShipment($shipment);

        if (null === $tracking) {
            $tracking = $this->shipmentTrackingFactory->createForShipment($shipment);
            $this->entityManager->persist($tracking);
        }

        $tracking->setCarrier($carrier);
        $tracking->setCarrierNameOther(
            null !== $carrier && $this->carrierProvider->isOther($carrier) ? $carrierNameOther : null,
        );
        $tracking->markAsPending();

        $this->entityManager->flush();
    }
}
