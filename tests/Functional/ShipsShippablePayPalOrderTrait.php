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

namespace Tests\Sylius\PayPalPlugin\Functional;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Tests\Sylius\PayPalPlugin\Service\DummyAddTrackingApi;
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;

trait ShipsShippablePayPalOrderTrait
{
    private const PAYPAL_ORDER_DETAILS = [
        'status' => 'COMPLETED',
        'purchase_units' => [
            [
                'items' => [['name' => 'Mug', 'quantity' => '1', 'sku' => 'MUG_SW']],
                'payments' => ['captures' => [['id' => 'CAPTURE_ID', 'status' => 'COMPLETED']]],
            ],
        ],
    ];

    private OrderInterface $order;

    /** @return array<string, object> */
    private function loadShippablePayPalOrder(): array
    {
        DummyAddTrackingApi::reset();
        DummyOrderDetailsApi::$failWith = null;
        DummyOrderDetailsApi::$response = self::PAYPAL_ORDER_DETAILS;

        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/shippable_paypal_order.yaml']);
        $this->order = $fixtures['shippable_order'];

        return $fixtures;
    }

    private function resetPayPalDummies(): void
    {
        DummyAddTrackingApi::reset();
        DummyOrderDetailsApi::$failWith = null;
        DummyOrderDetailsApi::$response = null;
    }

    private function shipment(): ShipmentInterface
    {
        self::getContainer()->get('doctrine.orm.entity_manager')->clear();

        /** @var ShipmentInterface $shipment */
        $shipment = self::getContainer()->get('sylius.repository.shipment')->find($this->order->getShipments()->first()->getId());

        return $shipment;
    }

    private function tracking(): ?ShipmentTrackingInterface
    {
        return self::getContainer()->get('sylius_paypal.repository.shipment_tracking')->findOneByShipment($this->shipment());
    }
}
