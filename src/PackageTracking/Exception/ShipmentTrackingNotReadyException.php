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

namespace Sylius\PayPalPlugin\PackageTracking\Exception;

final class ShipmentTrackingNotReadyException extends \Exception
{
    public function __construct(int|string|null $shipmentId, string $state)
    {
        parent::__construct(sprintf(
            'Shipment #%s is in the "%s" state instead of "shipped"; its transaction has most likely not been committed yet',
            (string) $shipmentId,
            $state,
        ));
    }
}
