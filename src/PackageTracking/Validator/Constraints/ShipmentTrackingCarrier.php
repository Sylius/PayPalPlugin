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

namespace Sylius\PayPalPlugin\PackageTracking\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ShipmentTrackingCarrier extends Constraint
{
    public string $carrierInvalidMessage = 'sylius_paypal.shipment_tracking.carrier_invalid';

    public string $carrierNameOtherRequiredMessage = 'sylius_paypal.shipment_tracking.carrier_name_other_required';

    public string $trackingCodeRequiredMessage = 'sylius_paypal.shipment_tracking.tracking_code_required';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return ShipmentTrackingCarrierValidator::class;
    }
}
