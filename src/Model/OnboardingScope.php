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

final readonly class OnboardingScope
{
    /**
     * @param list<string> $secondaryProducts
     * @param list<string> $capabilities
     * @param list<string> $features
     */
    public function __construct(
        private string $product,
        private array $secondaryProducts,
        private array $capabilities,
        private array $features,
    ) {
    }

    public static function current(): self
    {
        return new self(
            'ppcp',
            ['payment_methods'],
            ['APPLE_PAY', 'GOOGLE_PAY'],
            ['payment', 'refund', 'access_merchant_information'],
        );
    }

    public function product(): string
    {
        return $this->product;
    }

    /** @return list<string> */
    public function secondaryProducts(): array
    {
        return $this->secondaryProducts;
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    /** @return list<string> */
    public function features(): array
    {
        return $this->features;
    }
}
