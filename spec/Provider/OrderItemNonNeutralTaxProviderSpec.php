<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\Collection;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;

final class OrderItemNonNeutralTaxProviderSpec extends ObjectBehavior
{
    function it_provides_non_neutral_tax_based_on_given_order_item(
        OrderItemInterface $orderItem,
        Collection $adjustmentCollection,
        Collection $unitCollection,
        Collection $taxAdjustmentUnitCollection,
        AdjustmentInterface $adjustment,
        OrderItemUnitInterface $orderItemUnit,
        AdjustmentInterface $unitAdjustment
    ): void {
        $orderItem->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->willReturn($adjustmentCollection);
        $adjustment->isNeutral()->willReturn(true);
        $adjustment->getAmount()->shouldNotBeCalled();

        $adjustmentCollection->toArray()->willReturn([$adjustment]);

        $orderItem->getUnits()->willReturn($unitCollection);
        $unitCollection->toArray()->willReturn([$orderItemUnit]);
        $orderItemUnit->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)->willReturn($taxAdjustmentUnitCollection);
        $taxAdjustmentUnitCollection->toArray()->willReturn([$unitAdjustment]);

        $unitAdjustment->isNeutral()->willReturn(false);
        $unitAdjustment->getAmount()->willReturn(20);

        $this->provide($orderItem)->shouldReturn([20]);
    }
}
