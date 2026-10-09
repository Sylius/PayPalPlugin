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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\ShipmentTrackingItemsProvider;

final class ShipmentTrackingItemsProviderTest extends TestCase
{
    private ShipmentTrackingItemsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new ShipmentTrackingItemsProvider();
    }

    #[Test]
    public function it_builds_items_from_the_shipment_units_grouped_by_variant(): void
    {
        $shirtVariant = $this->createMock(ProductVariantInterface::class);
        $shirtVariant->method('getCode')->willReturn('sku01');
        $shirt = $this->createMock(OrderItemInterface::class);
        $shirt->method('getVariant')->willReturn($shirtVariant);
        $shirt->method('getProductName')->willReturn('T-Shirt');

        $shoesVariant = $this->createMock(ProductVariantInterface::class);
        $shoesVariant->method('getCode')->willReturn('sku02');
        $shoes = $this->createMock(OrderItemInterface::class);
        $shoes->method('getVariant')->willReturn($shoesVariant);
        $shoes->method('getProductName')->willReturn('Shoes');

        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getUnits')->willReturn(new ArrayCollection([
            $this->unitFor($shirt),
            $this->unitFor($shirt),
            $this->unitFor($shoes),
        ]));

        $items = $this->provider->provide($shipment);

        self::assertSame([
            ['name' => 'T-Shirt', 'quantity' => '2', 'sku' => 'sku01'],
            ['name' => 'Shoes', 'quantity' => '1', 'sku' => 'sku02'],
        ], $items);
    }

    #[Test]
    public function it_returns_an_empty_list_when_the_shipment_has_no_units(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getUnits')->willReturn(new ArrayCollection([]));

        self::assertSame([], $this->provider->provide($shipment));
    }

    private function unitFor(OrderItemInterface $orderItem): OrderItemUnitInterface
    {
        $unit = $this->createMock(OrderItemUnitInterface::class);
        $unit->method('getOrderItem')->willReturn($orderItem);

        return $unit;
    }
}
