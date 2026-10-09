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

final readonly class PayPalShippingOptions
{
    /** @var array<int, PayPalShippingOption> */
    private array $options;

    public function __construct(PayPalShippingOption ...$options)
    {
        $this->options = array_values($options);
    }

    public function isEmpty(): bool
    {
        return [] === $this->options;
    }

    public function selected(): ?PayPalShippingOption
    {
        foreach ($this->options as $option) {
            if ($option->isSelected()) {
                return $option;
            }
        }

        return null;
    }

    public function withSelected(string $id): self
    {
        $ids = array_map(static fn (PayPalShippingOption $option): string => $option->id(), $this->options);
        if (!in_array($id, $ids, true)) {
            return $this;
        }

        return new self(...array_map(
            static fn (PayPalShippingOption $option): PayPalShippingOption => $option->withSelected($id === $option->id()),
            $this->options,
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(
            static fn (PayPalShippingOption $option): array => $option->toArray(),
            $this->options,
        );
    }
}
