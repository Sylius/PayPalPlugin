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

final readonly class PayPalShippingOption
{
    public const TYPE_SHIPPING = 'SHIPPING';

    public const TYPE_PICKUP = 'PICKUP';

    public function __construct(
        private string $id,
        private string $label,
        private string $currencyCode,
        private int $amount,
        private bool $selected = false,
        private string $type = self::TYPE_SHIPPING,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function withSelected(bool $selected): self
    {
        return new self($this->id, $this->label, $this->currencyCode, $this->amount, $selected, $this->type);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amountToArray(),
            'type' => $this->type,
            'label' => $this->label,
            'selected' => $this->selected,
        ];
    }

    /** @return array<string, string> */
    public function amountToArray(): array
    {
        return [
            'currency_code' => $this->currencyCode,
            'value' => number_format($this->amount / 100, 2, '.', ''),
        ];
    }
}
