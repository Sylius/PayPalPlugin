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

namespace Sylius\PayPalPlugin\Model;

final readonly class PayPalWebhookEventNotification
{
    /** @param array<string, mixed> $resource */
    public function __construct(
        private string $id,
        private string $eventType,
        private string $resourceType,
        private string $resourceId,
        private array $resource,
    ) {
    }

    public static function fromJson(string $json): ?self
    {
        $event = json_decode($json, true);

        if (
            !is_array($event) ||
            !is_string($event['id'] ?? null) ||
            !is_string($event['event_type'] ?? null) ||
            !is_string($event['resource_type'] ?? null) ||
            !is_array($event['resource'] ?? null) ||
            !is_string($event['resource']['id'] ?? null)
        ) {
            return null;
        }

        return new self($event['id'], $event['event_type'], $event['resource_type'], $event['resource']['id'], $event['resource']);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function resourceType(): string
    {
        return $this->resourceType;
    }

    public function resourceId(): string
    {
        return $this->resourceId;
    }

    /** @return array<string, mixed> */
    public function resource(): array
    {
        return $this->resource;
    }
}
