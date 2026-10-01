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

namespace Sylius\PayPalPlugin\PackageTracking\Message\Handler;

use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Message\SendShipmentTracking;
use Sylius\PayPalPlugin\PackageTracking\Processor\ShipmentTrackingProcessorInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final readonly class SendShipmentTrackingHandler
{
    /** @param RepositoryInterface<ShipmentInterface> $shipmentRepository */
    public function __construct(
        private RepositoryInterface $shipmentRepository,
        private ShipmentTrackingProcessorInterface $shipmentTrackingProcessor,
    ) {
    }

    public function __invoke(SendShipmentTracking $message): void
    {
        $shipment = $this->shipmentRepository->find($message->shipmentId);
        if (!$shipment instanceof ShipmentInterface) {
            return;
        }

        $this->shipmentTrackingProcessor->process($shipment);
    }
}
