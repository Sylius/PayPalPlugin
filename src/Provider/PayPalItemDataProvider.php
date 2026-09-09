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

namespace Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface as CoreProductInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalItemDataProvider implements PayPalItemDataProviderInterface
{
    public const CATEGORY_PHYSICAL_GOODS = 'PHYSICAL_GOODS';

    public const CATEGORY_DIGITAL_GOODS = 'DIGITAL_GOODS';

    public function __construct(
        private OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider,
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

    public function provide(OrderInterface $order): array
    {
        $itemData = [
            'items' => [],
            'total_item_value' => 0,
            'total_tax' => 0,
        ];

        $currencyCode = (string) $order->getCurrencyCode();
        $category = $this->resolveCategory($order);

        /** @var Collection<int, OrderItemInterface> $orderItems */
        $orderItems = $order->getItems();

        foreach ($orderItems as $orderItem) {
            $productName = $this->truncateProductName($orderItem->getProductName());
            $quantity = $orderItem->getQuantity();
            if ($quantity <= 0) {
                continue;
            }

            $itemValue = $orderItem->getUnitPrice();

            $variant = $orderItem->getVariant();
            $product = $variant?->getProduct();
            $itemDetails = [
                'category' => $category,
                'sku' => $variant?->getCode(),
                'description' => $this->resolveDescription($product),
                'url' => $this->resolveProductUrl($product),
            ];

            $nonNeutralTaxes = $this->orderItemNonNeutralTaxesProvider->provide($orderItem);
            $totalTax = $nonNeutralTaxes !== [] ? array_sum($nonNeutralTaxes) : 0;

            $baseTax = (int) floor($totalTax / $quantity);
            $remainder = $totalTax % $quantity;

            if ($remainder === 0 || $quantity === 1) {
                $this->addItem($itemData, $productName, $quantity, $itemValue, $baseTax, $currencyCode, $itemDetails);
            } else {
                $this->addItem($itemData, $productName, $quantity - 1, $itemValue, $baseTax, $currencyCode, $itemDetails);
                $this->addItem($itemData, $productName, 1, $itemValue, $baseTax + $remainder, $currencyCode, $itemDetails);
            }
        }

        $itemData['total_item_value'] = number_format($itemData['total_item_value'] / 100, 2, '.', '');
        $itemData['total_tax'] = number_format($itemData['total_tax'] / 100, 2, '.', '');

        return $itemData;
    }

    /**
     * @param array{category: string, sku: string|null, description: string|null, url: string|null} $itemDetails
     */
    private function addItem(
        array &$itemData,
        string $productName,
        int $quantity,
        int $itemValue,
        int $tax,
        string $currencyCode,
        array $itemDetails,
    ): void {
        $itemData['total_item_value'] += $itemValue * $quantity;
        $itemData['total_tax'] += $tax * $quantity;

        $item = [
            'name' => $productName,
            'unit_amount' => [
                'value' => number_format($itemValue / 100, 2, '.', ''),
                'currency_code' => $currencyCode,
            ],
            'quantity' => $quantity,
            'tax' => [
                'value' => number_format($tax / 100, 2, '.', ''),
                'currency_code' => $currencyCode,
            ],
            'category' => $itemDetails['category'],
        ];

        if (null !== $itemDetails['sku']) {
            $item['sku'] = $itemDetails['sku'];
        }

        if (null !== $itemDetails['description']) {
            $item['description'] = $itemDetails['description'];
        }

        if (null !== $itemDetails['url']) {
            $item['url'] = $itemDetails['url'];
        }

        $itemData['items'][] = $item;
    }

    private function resolveCategory(OrderInterface $order): string
    {
        return $order->isShippingRequired() ? self::CATEGORY_PHYSICAL_GOODS : self::CATEGORY_DIGITAL_GOODS;
    }

    private function resolveDescription(?ProductInterface $product): ?string
    {
        if (!$product instanceof CoreProductInterface) {
            return null;
        }

        $shortDescription = $product->getShortDescription();
        if ($shortDescription === null || $shortDescription === '') {
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
        if ($slug === null || $slug === '') {
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
