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

namespace Sylius\PayPalPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepository;

#[ORM\Entity(repositoryClass: PayPalWebhookEventRepository::class)]
#[ORM\Table(name: 'sylius_paypal_plugin_webhook_event')]
#[ORM\Index(columns: ['created_at'])]
class PayPalWebhookEvent implements PayPalWebhookEventInterface
{
    #[ORM\Id]
    #[ORM\Column(name: 'event_id', type: 'string')]
    private string $eventId;

    #[ORM\Id]
    #[ORM\Column(name: 'payment_method_code', type: 'string')]
    private string $paymentMethodCode;

    #[ORM\Column(name: 'event_type', type: 'string')]
    private string $eventType;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: PayPalWebhookEventStatus::class)]
    private PayPalWebhookEventStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct(
        string $eventId,
        string $paymentMethodCode,
        string $eventType,
        PayPalWebhookEventStatus $status = PayPalWebhookEventStatus::Received,
    ) {
        $this->eventId = $eventId;
        $this->paymentMethodCode = $paymentMethodCode;
        $this->eventType = $eventType;
        $this->status = $status;
        $this->createdAt = new \DateTime();
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getPaymentMethodCode(): string
    {
        return $this->paymentMethodCode;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getStatus(): PayPalWebhookEventStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
