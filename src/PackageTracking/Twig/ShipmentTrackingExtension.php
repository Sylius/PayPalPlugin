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

namespace Sylius\PayPalPlugin\PackageTracking\Twig;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ShipmentTrackingExtension extends AbstractExtension
{
    public function __construct(
        private readonly ShipmentTrackingRepositoryInterface $shipmentTrackingRepository,
        private readonly OrderPayPalPaymentProviderInterface $orderPayPalPaymentProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_paypal_shipment_tracking', $this->getShipmentTracking(...)),
            new TwigFunction('sylius_paypal_shipment_is_paid_with_paypal', $this->isPaidWithPayPal(...)),
        ];
    }

    public function getShipmentTracking(ShipmentInterface $shipment): ?ShipmentTrackingInterface
    {
        return $this->shipmentTrackingRepository->findOneByShipment($shipment);
    }

    public function isPaidWithPayPal(ShipmentInterface $shipment): bool
    {
        $order = $shipment->getOrder();
        if (!$order instanceof OrderInterface) {
            return false;
        }

        return null !== $this->orderPayPalPaymentProvider->provide($order);
    }
}
