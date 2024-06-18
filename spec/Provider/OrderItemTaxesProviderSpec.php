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

namespace spec\Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;

final class OrderItemTaxesProviderSpec extends ObjectBehavior
{
    function it_allocates_neutral_order_item_taxes_to_the_units(
        OrderItemInterface $orderItem,
        AdjustmentInterface $neutralAdjustment,
        OrderItemUnitInterface $firstOrderItemUnit,
        OrderItemUnitInterface $secondOrderItemUnit,
        OrderItemUnitInterface $thirdOrderItemUnit,
    ): void {
        $orderItem
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([$neutralAdjustment->getWrappedObject()]))
        ;
        $orderItem->getQuantity()->shouldBeCalled()->willReturn(3);

        $neutralAdjustment->isNeutral()->willReturn(true);
        $neutralAdjustment->getAmount()->willReturn(332);

        $orderItem
            ->getUnits()
            ->willReturn(new ArrayCollection([
                $firstOrderItemUnit->getWrappedObject(),
                $secondOrderItemUnit->getWrappedObject(),
                $thirdOrderItemUnit->getWrappedObject(),
            ]))
        ;

        $firstOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection([]))
        ;
        $firstOrderItemUnit->getId()->shouldBeCalled()->willReturn(100);

        $secondOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection([]))
        ;
        $secondOrderItemUnit->getId()->shouldBeCalled()->willReturn(101);

        $thirdOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection([]))
        ;
        $thirdOrderItemUnit->getId()->shouldBeCalled()->willReturn(102);

        $this->provide($orderItem)->shouldReturn([
            'total' => 332,
            'itemTaxes' => [
                '100' => [0 => 0, 1 => 111],
                '101' => [0 => 0, 1 => 111],
                '102' => [0 => 0, 1 => 110],
            ],
        ]);
    }

    function it_allocates_non_neutral_order_item_taxes_to_the_units(
        OrderItemInterface $orderItem,
        AdjustmentInterface $nonNeutralAdjustment,
        OrderItemUnitInterface $firstOrderItemUnit,
        OrderItemUnitInterface $secondOrderItemUnit,
        OrderItemUnitInterface $thirdOrderItemUnit,
    ): void {
        $orderItem
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([$nonNeutralAdjustment->getWrappedObject()]))
        ;
        $orderItem->getQuantity()->shouldBeCalled()->willReturn(3);

        $nonNeutralAdjustment->isNeutral()->willReturn(false);
        $nonNeutralAdjustment->getAmount()->willReturn(557);

        $orderItem
            ->getUnits()
            ->willReturn(new ArrayCollection([
                $firstOrderItemUnit->getWrappedObject(),
                $secondOrderItemUnit->getWrappedObject(),
                $thirdOrderItemUnit->getWrappedObject(),
            ]))
        ;

        $firstOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection([]))
        ;
        $firstOrderItemUnit->getId()->shouldBeCalled()->willReturn(100);

        $secondOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection([]))
        ;
        $secondOrderItemUnit->getId()->shouldBeCalled()->willReturn(101);

        $thirdOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection([]))
        ;
        $thirdOrderItemUnit->getId()->shouldBeCalled()->willReturn(102);

        $this->provide($orderItem)->shouldReturn([
            'total' => 557,
            'itemTaxes' => [
                '100' => [0 => 186, 1 => 0],
                '101' => [0 => 186, 1 => 0],
                '102' => [0 => 185, 1 => 0],
            ],
        ]);
    }

    function it_provides_non_neutral_tax_based_on_given_order_item(
        OrderItemInterface $orderItem,
        OrderItemUnitInterface $firstOrderItemUnit,
        OrderItemUnitInterface $secondOrderItemUnit,
        OrderItemUnitInterface $thirdOrderItemUnit,
        AdjustmentInterface $firstNonNeutralAdjustment,
        AdjustmentInterface $secondNonNeutralAdjustment,
        AdjustmentInterface $thirdNonNeutralAdjustment,
    ): void {
        $orderItem
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([]))
        ;
        $orderItem->getQuantity()->shouldBeCalled()->willReturn(3);
        $orderItem
            ->getUnits()
            ->willReturn(new ArrayCollection([
                $firstOrderItemUnit->getWrappedObject(),
                $secondOrderItemUnit->getWrappedObject(),
                $thirdOrderItemUnit->getWrappedObject(),
            ]))
        ;

        $firstOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([$firstNonNeutralAdjustment->getWrappedObject()]))
        ;
        $firstOrderItemUnit->getId()->shouldBeCalled()->willReturn(100);

        $secondOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([$secondNonNeutralAdjustment->getWrappedObject()]))
        ;
        $secondOrderItemUnit->getId()->shouldBeCalled()->willReturn(101);

        $thirdOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([$thirdNonNeutralAdjustment->getWrappedObject()]))
        ;
        $thirdOrderItemUnit->getId()->shouldBeCalled()->willReturn(102);

        $firstNonNeutralAdjustment->isNeutral()->shouldBeCalled()->willReturn(false);
        $firstNonNeutralAdjustment->getAmount()->shouldBeCalled()->willReturn(20);

        $secondNonNeutralAdjustment->isNeutral()->shouldBeCalled()->willReturn(false);
        $secondNonNeutralAdjustment->getAmount()->shouldBeCalled()->willReturn(20);

        $thirdNonNeutralAdjustment->isNeutral()->shouldBeCalled()->willReturn(false);
        $thirdNonNeutralAdjustment->getAmount()->shouldBeCalled()->willReturn(19);

        $this->provide($orderItem)->shouldReturn([
            'total' => 59,
            'itemTaxes' => [
                '100' => [0 => 20, 1 => 0],
                '101' => [0 => 20, 1 => 0],
                '102' => [0 => 19, 1 => 0],
            ],
        ]);
    }

    function it_provides_neutral_tax_based_on_given_order_item(
        OrderItemInterface $orderItem,
        OrderItemUnitInterface $firstOrderItemUnit,
        OrderItemUnitInterface $secondOrderItemUnit,
        OrderItemUnitInterface $thirdOrderItemUnit,
        AdjustmentInterface $firstNeutralAdjustment,
        AdjustmentInterface $secondNeutralAdjustment,
        AdjustmentInterface $thirdNeutralAdjustment,
    ): void {
        $orderItem
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([]))
        ;
        $orderItem->getQuantity()->shouldBeCalled()->willReturn(3);
        $orderItem
            ->getUnits()
            ->willReturn(new ArrayCollection([
                $firstOrderItemUnit->getWrappedObject(),
                $secondOrderItemUnit->getWrappedObject(),
                $thirdOrderItemUnit->getWrappedObject(),
            ]))
        ;

        $firstOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([$firstNeutralAdjustment->getWrappedObject()]))
        ;
        $firstOrderItemUnit->getId()->shouldBeCalled()->willReturn(100);

        $secondOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([$secondNeutralAdjustment->getWrappedObject()]))
        ;
        $secondOrderItemUnit->getId()->shouldBeCalled()->willReturn(101);

        $thirdOrderItemUnit
            ->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT)
            ->shouldBeCalled()
            ->willReturn(new ArrayCollection([$thirdNeutralAdjustment->getWrappedObject()]))
        ;
        $thirdOrderItemUnit->getId()->shouldBeCalled()->willReturn(102);

        $firstNeutralAdjustment->isNeutral()->shouldBeCalled()->willReturn(true);
        $firstNeutralAdjustment->getAmount()->shouldBeCalled()->willReturn(30);

        $secondNeutralAdjustment->isNeutral()->shouldBeCalled()->willReturn(true);
        $secondNeutralAdjustment->getAmount()->shouldBeCalled()->willReturn(29);

        $thirdNeutralAdjustment->isNeutral()->shouldBeCalled()->willReturn(true);
        $thirdNeutralAdjustment->getAmount()->shouldBeCalled()->willReturn(29);

        $this->provide($orderItem)->shouldReturn([
            'total' => 88,
            'itemTaxes' => [
                '100' => [0 => 0, 1 => 30],
                '101' => [0 => 0, 1 => 29],
                '102' => [0 => 0, 1 => 29],
            ],
        ]);
    }
}
