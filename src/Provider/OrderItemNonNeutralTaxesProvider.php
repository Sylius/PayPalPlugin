<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;

final class OrderItemNonNeutralTaxesProvider implements OrderItemNonNeutralTaxesProviderInterface
{
    public function provide(OrderItemInterface $orderItem): iterable
    {
        $taxes = [];

        /** @var Collection<int, AdjustmentInterface> $orderItemTaxAdjustments */
        $orderItemTaxAdjustments = $orderItem->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);
        foreach ($orderItemTaxAdjustments as $taxAdjustment) {
            if (!$taxAdjustment->isNeutral()) {
                $taxes[] = $taxAdjustment->getAmount();
            }
        }

        /** @var Collection<array-key, OrderItemUnitInterface> $orderItemUnits */
        $orderItemUnits = $orderItem->getUnits();

        foreach ($orderItemUnits as $unit) {
            /** @var Collection<int, AdjustmentInterface> $unitAdjustments */
            $unitAdjustments = $unit->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);

            foreach ($unitAdjustments as $taxAdjustment) {
                if (!$taxAdjustment->isNeutral()) {
                    $taxes[] = $taxAdjustment->getAmount();
                }
            }
        }

        return (empty($taxes)) ? [0] : $taxes;
    }
}
