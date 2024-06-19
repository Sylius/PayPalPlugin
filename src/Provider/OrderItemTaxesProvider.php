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

use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class OrderItemTaxesProvider implements OrderItemTaxesProviderInterface
{
    public function provide(OrderItemInterface $orderItem): array
    {
        $orderItemTaxes = [0 => 0, 1 => 0];
        foreach ($orderItem->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            $orderItemTaxes[(int) $taxAdjustment->isNeutral()] += $taxAdjustment->getAmount();
        }

        [0 => $nonNeutralItemTaxes, 1 => $neutralItemTaxes] = $orderItemTaxes;

        $totalTaxes = ['total' => 0, 'itemTaxes' => []];
        $unitCount = $orderItem->getQuantity();

        foreach ($orderItem->getUnits() as $unit) {
            $unitId = (string) $unit->getId();

            $nonNeutralTaxAllocation = (int) ceil($nonNeutralItemTaxes / $unitCount);
            $neutralTaxAllocation = (int) ceil($neutralItemTaxes / $unitCount);

            $totalTaxes['itemTaxes'][$unitId][0] = $nonNeutralTaxAllocation;
            $totalTaxes['itemTaxes'][$unitId][1] = $neutralTaxAllocation;

            $totalTaxes['total'] += $nonNeutralTaxAllocation;
            $totalTaxes['total'] += $neutralTaxAllocation;

            $nonNeutralItemTaxes -= $nonNeutralTaxAllocation;
            $neutralItemTaxes -= $neutralTaxAllocation;

            --$unitCount;

            foreach ($unit->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
                $totalTaxes['itemTaxes'][$unitId][(int) $taxAdjustment->isNeutral()] += $taxAdjustment->getAmount();
                $totalTaxes['total'] += $taxAdjustment->getAmount();
            }
        }

        return $totalTaxes;
    }
}
