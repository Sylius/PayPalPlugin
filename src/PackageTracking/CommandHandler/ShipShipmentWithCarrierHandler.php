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

namespace Sylius\PayPalPlugin\PackageTracking\CommandHandler;

use Sylius\Bundle\ApiBundle\Command\Checkout\ShipShipment;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Repository\ShipmentRepositoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Command\ShipShipmentWithCarrier;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Webmozart\Assert\Assert;

final readonly class ShipShipmentWithCarrierHandler
{
    /** @param ShipmentRepositoryInterface<ShipmentInterface> $shipmentRepository */
    public function __construct(
        private object $decoratedHandler,
        private ShipmentRepositoryInterface $shipmentRepository,
        private OrderPayPalPaymentProviderInterface $orderPayPalPaymentProvider,
        private ShipmentTrackingManagerInterface $shipmentTrackingManager,
    ) {
    }

    public function __invoke(ShipShipment $shipShipment): ShipmentInterface
    {
        if ($shipShipment instanceof ShipShipmentWithCarrier) {
            $this->updateCarrier($shipShipment);
        }

        $decoratedHandler = $this->decoratedHandler;
        Assert::isCallable($decoratedHandler);

        $shipment = $decoratedHandler($shipShipment);
        Assert::isInstanceOf($shipment, ShipmentInterface::class);

        return $shipment;
    }

    private function updateCarrier(ShipShipmentWithCarrier $shipShipment): void
    {
        $trackingData = new ShipmentTrackingData($shipShipment->carrier, $shipShipment->carrierNameOther);
        if (null === $trackingData->getCarrier()) {
            return;
        }

        $shipment = $this->shipmentRepository->find($shipShipment->shipmentId);
        $order = $shipment?->getOrder();
        if (!$order instanceof OrderInterface || null === $this->orderPayPalPaymentProvider->provide($order)) {
            return;
        }

        $this->shipmentTrackingManager->updateCarrier($shipment, $trackingData->getCarrier(), $trackingData->getCarrierNameOther());
    }
}
