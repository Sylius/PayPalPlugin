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
    ) {
    }

    /** @param array<string, mixed> $payPalOrderDetails */
    public static function completedFromPayPalOrder(array $payPalOrderDetails): ?self
    {
        $refunds = $payPalOrderDetails['purchase_units'][0]['payments']['refunds'] ?? null;

        foreach (array_reverse(is_array($refunds) ? $refunds : []) as $refund) {
            if (is_array($refund) && is_string($refund['id'] ?? null) && self::STATUS_COMPLETED === ($refund['status'] ?? null)) {
                return new self($refund['id'], self::STATUS_COMPLETED);
            }
        }

        return null;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function status(): string
    {
        return $this->status;
    }
}
