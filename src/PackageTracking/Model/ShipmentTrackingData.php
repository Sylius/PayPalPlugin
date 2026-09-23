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

namespace Sylius\PayPalPlugin\PackageTracking\Model;

class ShipmentTrackingData
{
    private ?string $carrier = null;

    private ?string $carrierNameOther = null;

    private ?string $trackingNumber = null;

    public function __construct(?string $carrier = null, ?string $carrierNameOther = null, ?string $trackingNumber = null)
    {
        $this->setCarrier($carrier);
        $this->setCarrierNameOther($carrierNameOther);
        $this->setTrackingNumber($trackingNumber);
    }

    public function getCarrier(): ?string
    {
        return $this->carrier;
    }

    public function setCarrier(?string $carrier): void
    {
        $this->carrier = self::normalize($carrier);
    }

    public function getCarrierNameOther(): ?string
    {
        return $this->carrierNameOther;
    }

    public function setCarrierNameOther(?string $carrierNameOther): void
    {
        $this->carrierNameOther = self::normalize($carrierNameOther);
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): void
    {
        $this->trackingNumber = self::normalize($trackingNumber);
    }

    private static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
