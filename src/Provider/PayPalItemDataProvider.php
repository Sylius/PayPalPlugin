<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class PayPalItemDataProvider implements PayPalItemDataProviderInterface
{
    private OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider;

    public function __construct(OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider)
    {
        $this->orderItemNonNeutralTaxesProvider = $orderItemNonNeutralTaxesProvider;
    }

    public function provide(OrderInterface $order): array
    {
        $itemData = [
            'items' => [],
            'total_item_value' => 0,
            'total_tax' => 0,
        ];

        /** @var Collection<int, OrderItemInterface> $orderItems */
        $orderItems = $order->getItems();

        foreach ($orderItems as $orderItem) {
            $nonNeutralTaxes = $this->orderItemNonNeutralTaxesProvider->provide($orderItem);
            /** @var int $nonNeutralTax */
            foreach ($nonNeutralTaxes as $nonNeutralTax) {
                $displayQuantity = $nonNeutralTaxes === [0] ? $orderItem->getQuantity() : 1;
                $itemValue = $orderItem->getUnitPrice();
                $itemData['total_item_value'] += ($itemValue * $displayQuantity) / 100;
                $itemData['total_tax'] += ($nonNeutralTax * $displayQuantity) / 100;

                $itemData['items'][] = [
                    'name' => $orderItem->getProductName(),
                    'unit_amount' => [
                        'value' => number_format($itemValue / 100, 2, '.', ''),
                        'currency_code' => $order->getCurrencyCode(),
                    ],
                    'quantity' => $displayQuantity,
                    'tax' => [
                        'value' => number_format($nonNeutralTax / 100, 2, '.', ''),
                        'currency_code' => $order->getCurrencyCode(),
                    ],
                ];

            }
        }

        $itemData['total_item_value'] = number_format($itemData['total_item_value'], 2, '.', '');
        $itemData['total_tax'] = number_format($itemData['total_tax'], 2, '.', '');

        return $itemData;
    }
}
