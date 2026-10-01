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

final readonly class PayPalItem
{
    public function __construct(
        private string $name,
        private int $quantity,
        private int $unitPrice,
        private int $tax,
        private string $currencyCode,
        private string $category,
        private ?string $sku = null,
        private ?string $description = null,
        private ?string $url = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $item = [
            'name' => $this->name,
            'unit_amount' => [
                'value' => number_format($this->unitPrice / 100, 2, '.', ''),
                'currency_code' => $this->currencyCode,
            ],
            'quantity' => $this->quantity,
            'tax' => [
                'value' => number_format($this->tax / 100, 2, '.', ''),
                'currency_code' => $this->currencyCode,
            ],
            'category' => $this->category,
        ];

        if (null !== $this->sku) {
            $item['sku'] = $this->sku;
        }

        if (null !== $this->description) {
            $item['description'] = $this->description;
        }

        if (null !== $this->url) {
            $item['url'] = $this->url;
        }

        return $item;
    }
}
