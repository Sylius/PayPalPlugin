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

use Sylius\Component\Core\Model\OrderInterface;

final class PayPalItemDataProvider implements PayPalItemDataProviderInterface
{
    public function __construct(private readonly OrderItemTaxesProviderInterface $orderItemTaxesProvider)
    {
    }

    public function provide(OrderInterface $order): array
    {
        $itemData = [
            'items' => [],
            'total_item_value' => 0,
            'total_tax' => 0,
        ];

        $currencyCode = (string) $order->getCurrencyCode();

        foreach ($order->getItems() as $orderItem) {
            $productName = (string) $orderItem->getProductName();
            $itemValue = $orderItem->getUnitPrice();

            $taxes = $this->orderItemTaxesProvider->provide($orderItem);

            if ($taxes['total'] === 0) {
                $this->addItem($itemData, $productName, $orderItem->getQuantity(), $itemValue, 0, $currencyCode);

                continue;
            }

            foreach ($taxes['itemTaxes'] as $itemTaxes) {
                $this->addItem(
                    $itemData,
                    $productName,
                    1,
                    $itemValue - $itemTaxes[1],
                    array_sum($itemTaxes),
                    $currencyCode,
                );
            }
        }

        $itemData['total_item_value'] = number_format($itemData['total_item_value'] / 100, 2, '.', '');
        $itemData['total_tax'] = number_format($itemData['total_tax'] / 100, 2, '.', '');

        return $itemData;
    }

    private function addItem(
        array &$itemData,
        string $productName,
        int $quantity,
        int $itemValue,
        int $tax,
        string $currencyCode,
    ): void {
        $itemData['total_item_value'] += $itemValue * $quantity;
        $itemData['total_tax'] += $tax * $quantity;

        $itemData['items'][] = [
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
        ];
    }
}
