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

final readonly class PayPalRefund
{
    public const STATUS_COMPLETED = 'COMPLETED';

    public function __construct(
        private string $id,
        private string $status,
        private ?string $captureId = null,
    ) {
    }

    /** @param array<string, mixed> $refund */
    public static function fromRefund(array $refund): ?self
    {
        if (!is_string($refund['id'] ?? null) || !is_string($refund['status'] ?? null)) {
            return null;
        }

        return new self($refund['id'], $refund['status'], self::captureIdOf($refund));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isCompleted(): bool
    {
        return self::STATUS_COMPLETED === $this->status;
    }

    public function captureId(): ?string
    {
        return $this->captureId;
    }

    /** @param array<string, mixed> $refund */
    private static function captureIdOf(array $refund): ?string
    {
        $links = $refund['links'] ?? [];
        foreach (is_array($links) ? $links : [] as $link) {
            if (
                is_array($link) &&
                'up' === ($link['rel'] ?? null) &&
                is_string($link['href'] ?? null) &&
                1 === preg_match('#/v2/payments/captures/([^/?]+)$#', (string) parse_url($link['href'], \PHP_URL_PATH), $matches)
            ) {
                return $matches[1];
            }
        }

        return null;
    }
}
