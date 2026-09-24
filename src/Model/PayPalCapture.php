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

use Sylius\PayPalPlugin\AmountUtils;

final readonly class PayPalCapture
{
    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_DECLINED = 'DECLINED';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_PENDING = 'PENDING';

    public function __construct(
        private string $status,
        private ?string $id = null,
        private ?int $amount = null,
        private ?string $currencyCode = null,
    ) {
    }

    /** @param array<string, mixed> $payPalOrderDetails */
    public static function fromPayPalOrder(array $payPalOrderDetails): ?self
    {
        $capture = $payPalOrderDetails['purchase_units'][0]['payments']['captures'][0] ?? null;

        if (!is_array($capture) || !is_string($capture['status'] ?? null)) {
            return null;
        }

        $amount = is_array($capture['amount'] ?? null) ? $capture['amount'] : [];

        return new self(
            $capture['status'],
            isset($capture['id']) ? (string) $capture['id'] : null,
            isset($amount['value']) ? AmountUtils::toMinorUnits((string) $amount['value']) : null,
            isset($amount['currency_code']) ? (string) $amount['currency_code'] : null,
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

    public function amount(): ?int
    {
        return $this->amount;
    }

    public function currencyCode(): ?string
    {
        return $this->currencyCode;
    }
}
