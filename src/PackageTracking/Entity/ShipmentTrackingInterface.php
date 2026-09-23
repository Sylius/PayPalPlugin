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

namespace Sylius\PayPalPlugin\PackageTracking\Entity;

use Sylius\Component\Core\Model\ShipmentInterface;

interface ShipmentTrackingInterface
{
    public const STATE_PENDING = 'pending';

    public const STATE_SYNCED = 'synced';

    public const STATE_FAILED = 'failed';

    public function getId(): ?int;

    public function getShipment(): ShipmentInterface;

    public function getCarrier(): ?string;

    public function setCarrier(?string $carrier): void;

    public function getCarrierNameOther(): ?string;

    public function setCarrierNameOther(?string $carrierNameOther): void;

    public function getPayPalTrackerId(): ?string;

    public function setPayPalTrackerId(?string $payPalTrackerId): void;

    public function getState(): string;

    public function getAttempts(): int;

    public function getLastError(): ?string;

    public function getCreatedAt(): \DateTimeInterface;

    public function getUpdatedAt(): ?\DateTimeInterface;

    public function markAsSynced(?string $payPalTrackerId): void;

    public function markAsFailed(string $error): void;

    public function markAsPending(): void;
}
