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

namespace Sylius\PayPalPlugin\PackageTracking\Factory;

use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;

interface ShipmentTrackingFactoryInterface
{
    public function createForShipment(ShipmentInterface $shipment): ShipmentTrackingInterface;
}
