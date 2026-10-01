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

use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProviderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ShipmentTrackingCarrierValidator extends ConstraintValidator
{
    public function __construct(private readonly CarrierProviderInterface $carrierProvider)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ShipmentTrackingCarrier) {
            throw new UnexpectedTypeException($constraint, ShipmentTrackingCarrier::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof ShipmentTrackingData) {
            throw new UnexpectedValueException($value, ShipmentTrackingData::class);
        }

        $carrier = $value->getCarrier();

        if (null === $carrier && null !== $value->getTrackingNumber()) {
            $this->context
                ->buildViolation($constraint->carrierRequiredMessage)
                ->atPath('carrier')
                ->addViolation()
            ;
        }

        if (null !== $carrier && null === $value->getCarrierNameOther() && $this->carrierProvider->isOther($carrier)) {
            $this->context
                ->buildViolation($constraint->carrierNameOtherRequiredMessage)
                ->atPath('carrierNameOther')
                ->addViolation()
            ;
        }
    }
}
