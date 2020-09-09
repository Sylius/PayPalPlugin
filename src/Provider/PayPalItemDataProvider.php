<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class PayPalItemDataProvider implements PayPalItemDataProviderInterface
{
    /** @var OrderItemNonNeutralTaxesProviderInterface */
    private $orderItemNonNeutralTaxesProvider;

    public function __construct(OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider)
    {
        $this->orderItemNonNeutralTaxesProvider = $orderItemNonNeutralTaxesProvider;
    }

    public function provide(OrderInterface $order): array
    {
        $payPalItemData = [
            'items' => [],
            'total_item_value' => 0,
            'total_tax' => 0,
        ];

        $orderItems = $order->getItems()->toArray();

        /** @var OrderItemInterface $orderItem */
        foreach ($orderItems as $orderItem) {
            $nonNeutralTaxes = $this->orderItemNonNeutralTaxesProvider->provide($orderItem);
            /** @var int $nonNeutralTax */
            foreach ($nonNeutralTaxes as $nonNeutralTax) {
                $displayQuantity = $nonNeutralTaxes === [0] ? $orderItem->getQuantity() : 1;
                $itemValue = $orderItem->getUnitPrice();
                $payPalItemData['total_item_value'] += ($itemValue * $displayQuantity) / 100;
                $payPalItemData['total_tax'] += ($nonNeutralTax * $displayQuantity) / 100;

                $payPalItemData['items'][] = [
                    'name' => $orderItem->getProductName(),
                    'unit_amount' => [
                        'value' => $itemValue / 100,
                        'currency_code' => $order->getCurrencyCode(),
                    ],
                    'quantity' => $displayQuantity,
                    'tax' => [
                        'value' => $nonNeutralTax / 100,
                        'currency_code' => $order->getCurrencyCode(),
                    ],
                ];
            }
        }

        return $payPalItemData;
    }
}
