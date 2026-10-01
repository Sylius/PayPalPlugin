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

namespace Sylius\PayPalPlugin\PackageTracking\EventListener\Workflow;

use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Dispatcher\ShipmentTrackingDispatcherInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

final readonly class SendShipmentTrackingListener
{
    public function __construct(private ShipmentTrackingDispatcherInterface $shipmentTrackingDispatcher)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $shipment = $event->getSubject();
        Assert::isInstanceOf($shipment, ShipmentInterface::class);

        $this->shipmentTrackingDispatcher->dispatch($shipment);
    }
}
