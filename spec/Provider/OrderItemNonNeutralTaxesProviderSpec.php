<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;

final class OrderItemNonNeutralTaxesProviderSpec extends ObjectBehavior
{
    function it_provides_non_neutral_tax_based_on_given_order_item(
        OrderItemInterface $orderItem,
        AdjustmentInterface $adjustment,
        OrderItemUnitInterface $orderItemUnit,
        AdjustmentInterface $unitAdjustment
    ): void {
        $orderItem->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection([$adjustment->getWrappedObject()]));

        $adjustment->isNeutral()->willReturn(true);
        $adjustment->getAmount()->shouldNotBeCalled();

        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->getWrappedObject()]));
        $orderItemUnit->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection([$unitAdjustment->getWrappedObject()]));

        $unitAdjustment->isNeutral()->willReturn(false);
        $unitAdjustment->getAmount()->willReturn(20);

        $this->provide($orderItem)->shouldReturn([20]);
    }
}
