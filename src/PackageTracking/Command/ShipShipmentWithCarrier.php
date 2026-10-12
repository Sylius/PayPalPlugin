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

namespace Sylius\PayPalPlugin\PackageTracking\Command;

use Sylius\Bundle\ApiBundle\Attribute\ShipmentIdAware;
use Sylius\Bundle\ApiBundle\Command\Checkout\ShipShipment;

#[ShipmentIdAware]
class ShipShipmentWithCarrier extends ShipShipment
{
    public function __construct(
        mixed $shipmentId = null,
        ?string $trackingCode = null,
        public readonly ?string $carrier = null,
        public readonly ?string $carrierNameOther = null,
    ) {
        parent::__construct($shipmentId, $trackingCode);
    }
}
