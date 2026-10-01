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

namespace Sylius\PayPalPlugin\PackageTracking\Provider;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Order\Model\OrderItemUnitInterface;

final class ShipmentTrackingItemsProvider implements ShipmentTrackingItemsProviderInterface
{
    public function provide(ShipmentInterface $shipment): array
    {
        /** @var array<string, array{sku: ?string, name: string, quantity: int}> $itemsByVariant */
        $itemsByVariant = [];

        foreach ($shipment->getUnits() as $unit) {
            if (!$unit instanceof OrderItemUnitInterface) {
                continue;
            }

            $orderItem = $unit->getOrderItem();
            if (!$orderItem instanceof OrderItemInterface) {
                continue;
            }

            $variant = $orderItem->getVariant();
            $sku = $variant?->getCode();
            $key = $sku ?? ('__no_sku__' . spl_object_id($orderItem));

            if (!isset($itemsByVariant[$key])) {
                $itemsByVariant[$key] = [
                    'sku' => $sku,
                    'name' => (string) $orderItem->getProductName(),
                    'quantity' => 0,
                ];
            }

            ++$itemsByVariant[$key]['quantity'];
        }

        $items = [];
        foreach ($itemsByVariant as $item) {
            $payload = [
                'name' => $item['name'],
                'quantity' => (string) $item['quantity'],
            ];

            if (null !== $item['sku']) {
                $payload['sku'] = $item['sku'];
            }

            $items[] = $payload;
        }

        return $items;
    }
}
