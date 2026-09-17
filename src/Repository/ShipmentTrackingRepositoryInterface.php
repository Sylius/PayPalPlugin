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

namespace Sylius\PayPalPlugin\Repository;

use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Entity\ShipmentTrackingInterface;

interface ShipmentTrackingRepositoryInterface
{
    public function findOneByShipment(ShipmentInterface $shipment): ?ShipmentTrackingInterface;

    /** @return ShipmentTrackingInterface[] */
    public function findPendingOrFailed(): array;
}
