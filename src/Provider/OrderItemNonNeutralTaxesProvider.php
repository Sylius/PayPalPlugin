<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class OrderItemNonNeutralTaxesProvider implements OrderItemNonNeutralTaxesProviderInterface
{
    public function provide(OrderItemInterface $orderItem): array
    {
        $taxes = [];

        foreach ($orderItem->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->toArray() as $taxAdjustment) {
            if (!$taxAdjustment->isNeutral()) {
                $taxes[] = $taxAdjustment->getAmount();
            }
        }

        foreach ($orderItem->getUnits()->toArray() as $unit) {
            foreach ($unit->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->toArray() as $taxAdjustment) {
                if (!$taxAdjustment->isNeutral()) {
                    $taxes[] = $taxAdjustment->getAmount();
                }
            }
        }

        return $taxes === [] ? [0] : $taxes;
    }
}
