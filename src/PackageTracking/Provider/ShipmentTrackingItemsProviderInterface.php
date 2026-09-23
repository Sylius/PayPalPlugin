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

namespace Sylius\PayPalPlugin\PackageTracking\Provider;

use Sylius\Component\Core\Model\ShipmentInterface;

interface ShipmentTrackingItemsProviderInterface
{
    /**
     * @return array<array<string, mixed>>
     */
    public function provide(ShipmentInterface $shipment): array;
}
