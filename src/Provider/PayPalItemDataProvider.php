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
use Sylius\PayPalPlugin\Factory\PayPalItemFactory;
use Sylius\PayPalPlugin\Factory\PayPalItemFactoryInterface;

final readonly class PayPalItemDataProvider implements PayPalItemDataProviderInterface
{
    public const CATEGORY_PHYSICAL_GOODS = 'PHYSICAL_GOODS';

    public const CATEGORY_DIGITAL_GOODS = 'DIGITAL_GOODS';

    private PayPalItemFactoryInterface $itemFactory;

    public function __construct(
        private OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider,
        ?PayPalItemFactoryInterface $itemFactory = null,
    ) {
        if (null === $itemFactory) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing a $itemFactory to "%s" constructor is deprecated and will be prohibited in 3.0.',
                self::class,
            );
        }

        $this->itemFactory = $itemFactory ?? new PayPalItemFactory();
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
            $quantity = $orderItem->getQuantity();
            if ($quantity <= 0) {
                continue;
            }

            $unitPrice = $orderItem->getUnitPrice();

            $nonNeutralTaxes = $this->orderItemNonNeutralTaxesProvider->provide($orderItem);
            $totalTax = [] !== $nonNeutralTaxes ? array_sum($nonNeutralTaxes) : 0;

            $baseTax = (int) floor($totalTax / $quantity);
            $remainder = $totalTax % $quantity;

            if (0 === $remainder || 1 === $quantity) {
                $this->addItem($itemData, $orderItem, $quantity, $unitPrice, $baseTax, $currencyCode, $category);
            } else {
                $this->addItem($itemData, $orderItem, $quantity - 1, $unitPrice, $baseTax, $currencyCode, $category);
                $this->addItem($itemData, $orderItem, 1, $unitPrice, $baseTax + $remainder, $currencyCode, $category);
            }
        }

        $itemData['total_item_value'] = number_format($itemData['total_item_value'] / 100, 2, '.', '');
        $itemData['total_tax'] = number_format($itemData['total_tax'] / 100, 2, '.', '');

        return $itemData;
    }

    private function addItem(
        array &$itemData,
        OrderItemInterface $orderItem,
        int $quantity,
        int $unitPrice,
        int $tax,
        string $currencyCode,
        string $category,
    ): void {
        $itemData['total_item_value'] += $unitPrice * $quantity;
        $itemData['total_tax'] += $tax * $quantity;

        $itemData['items'][] = $this->itemFactory->create(
            $orderItem,
            $quantity,
            $unitPrice,
            $tax,
            $currencyCode,
            $category,
        )->toArray();
    }

    private function resolveCategory(OrderInterface $order): string
    {
        return $order->isShippingRequired() ? self::CATEGORY_PHYSICAL_GOODS : self::CATEGORY_DIGITAL_GOODS;
    }
}
