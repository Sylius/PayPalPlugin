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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface as CoreProductInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\PayPalPlugin\Model\PayPalItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalItemFactory implements PayPalItemFactoryInterface
{
    public function __construct(
        private ?UrlGeneratorInterface $urlGenerator = null,
    ) {
        if (null === $urlGenerator) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing a $urlGenerator to "%s" constructor is deprecated and will be prohibited in 3.0.',
                self::class,
            );
        }
    }

    public function create(
        OrderItemInterface $orderItem,
        int $quantity,
        int $unitPrice,
        int $tax,
        string $currencyCode,
        string $category,
    ): PayPalItem {
        $variant = $orderItem->getVariant();
        $product = $variant?->getProduct();

        return new PayPalItem(
            $this->truncateProductName($orderItem->getProductName()),
            $quantity,
            $unitPrice,
            $tax,
            $currencyCode,
            $category,
            $variant?->getCode(),
            $this->resolveDescription($product),
            $this->resolveProductUrl($product),
        );
    }

    private function resolveDescription(?ProductInterface $product): ?string
    {
        if (!$product instanceof CoreProductInterface) {
            return null;
        }

        $shortDescription = $product->getShortDescription();
        if (null === $shortDescription || '' === $shortDescription) {
            return null;
        }

        return mb_strlen($shortDescription) > 127
            ? mb_substr($shortDescription, 0, 124) . '...'
            : $shortDescription;
    }

    private function resolveProductUrl(?ProductInterface $product): ?string
    {
        if (null === $this->urlGenerator) {
            return null;
        }

        $slug = $product?->getSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }

        try {
            return $this->urlGenerator->generate(
                'sylius_shop_product_show',
                ['slug' => $slug],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function truncateProductName(string $productName): string
    {
        return mb_strlen($productName) > 127
            ? mb_substr($productName, 0, 124) . '...'
            : $productName;
    }
}
