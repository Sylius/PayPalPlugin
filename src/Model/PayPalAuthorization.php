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

final readonly class PayPalAuthorization
{
    public const STATUS_CREATED = 'CREATED';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_DENIED = 'DENIED';

    public const STATUS_CAPTURED = 'CAPTURED';

    public const STATUS_PARTIALLY_CAPTURED = 'PARTIALLY_CAPTURED';

    public const STATUS_VOIDED = 'VOIDED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public function __construct(
        private string $status,
        private ?string $id = null,
    ) {
    }

    /** @param array<string, mixed> $payPalOrderDetails */
    public static function fromPayPalOrder(array $payPalOrderDetails): ?self
    {
        $authorization = $payPalOrderDetails['purchase_units'][0]['payments']['authorizations'][0] ?? null;

        if (!is_array($authorization) || !is_string($authorization['status'] ?? null)) {
            return null;
        }

        return new self(
            $authorization['status'],
            isset($authorization['id']) ? (string) $authorization['id'] : null,
        );
    }

    public function status(): string
    {
        return $this->status;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function isStatus(string $status): bool
    {
        return $status === $this->status;
    }
}
