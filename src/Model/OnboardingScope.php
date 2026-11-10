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
    private const PRODUCT = 'product';

    private const SECONDARY_PRODUCTS = 'secondary_products';

    private const CAPABILITIES = 'capabilities';

    private const FEATURES = 'features';

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

    /** @param array<array-key, mixed> $scope */
    public static function fromArray(array $scope): self
    {
        return new self(
            is_string($scope[self::PRODUCT] ?? null) ? $scope[self::PRODUCT] : '',
            self::listOf($scope[self::SECONDARY_PRODUCTS] ?? []),
            self::listOf($scope[self::CAPABILITIES] ?? []),
            self::listOf($scope[self::FEATURES] ?? []),
        );
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

    /** @return list<string> */
    public function notCoveredBy(self $scope): array
    {
        return array_merge(
            $this->product === $scope->product ? [] : [$this->product],
            array_diff($this->secondaryProducts, $scope->secondaryProducts),
            array_diff($this->capabilities, $scope->capabilities),
            array_diff($this->features, $scope->features),
        );
    }

    /** @return array{product: string, secondary_products: list<string>, capabilities: list<string>, features: list<string>} */
    public function toArray(): array
    {
        return [
            self::PRODUCT => $this->product,
            self::SECONDARY_PRODUCTS => $this->secondaryProducts,
            self::CAPABILITIES => $this->capabilities,
            self::FEATURES => $this->features,
        ];
    }

    /** @return list<string> */
    private static function listOf(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_map(strval(...), array_filter($values, is_scalar(...))));
    }
}
