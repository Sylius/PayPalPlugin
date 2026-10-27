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

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepository;

#[ORM\Entity(repositoryClass: ShipmentTrackingRepository::class)]
#[ORM\Table(name: 'sylius_paypal_plugin_shipment_tracking')]
class ShipmentTracking implements ShipmentTrackingInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: ShipmentInterface::class)]
    #[ORM\JoinColumn(name: 'shipment_id', referencedColumnName: 'id', unique: true, nullable: false, onDelete: 'CASCADE')]
    private ShipmentInterface $shipment;

    #[ORM\Column(name: 'carrier', type: 'string', nullable: true)]
    private ?string $carrier = null;

    #[ORM\Column(name: 'carrier_name_other', type: 'string', nullable: true)]
    private ?string $carrierNameOther = null;

    #[ORM\Column(name: 'paypal_tracker_id', type: 'string', nullable: true)]
    private ?string $payPalTrackerId = null;

    #[ORM\Column(name: 'state', type: 'string', length: 32)]
    private string $state = self::STATE_PENDING;

    #[ORM\Column(name: 'attempts', type: 'integer', options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct(ShipmentInterface $shipment)
    {
        $this->shipment = $shipment;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShipment(): ShipmentInterface
    {
        return $this->shipment;
    }

    public function getCarrier(): ?string
    {
        return $this->carrier;
    }

    public function setCarrier(?string $carrier): void
    {
        $this->carrier = $carrier;
        $this->touch();
    }

    public function getCarrierNameOther(): ?string
    {
        return $this->carrierNameOther;
    }

    public function setCarrierNameOther(?string $carrierNameOther): void
    {
        $this->carrierNameOther = $carrierNameOther;
        $this->touch();
    }

    public function getPayPalTrackerId(): ?string
    {
        return $this->payPalTrackerId;
    }

    public function setPayPalTrackerId(?string $payPalTrackerId): void
    {
        $this->payPalTrackerId = $payPalTrackerId;
        $this->touch();
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function markAsSynced(?string $payPalTrackerId): void
    {
        $this->state = self::STATE_SYNCED;
        $this->payPalTrackerId = $payPalTrackerId;
        $this->lastError = null;
        ++$this->attempts;
        $this->touch();
    }

    public function markAsFailed(string $error): void
    {
        $this->state = self::STATE_FAILED;
        $this->lastError = $error;
        ++$this->attempts;
        $this->touch();
    }

    public function markAsPending(): void
    {
        $this->state = self::STATE_PENDING;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
