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

namespace Sylius\PayPalPlugin\PackageTracking\Dispatcher;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Message\SendShipmentTracking;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class ShipmentTrackingDispatcher implements ShipmentTrackingDispatcherInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(ShipmentInterface $shipment): void
    {
        $shipmentId = $shipment->getId();
        if (null === $shipmentId) {
            return;
        }

        try {
            $this->messageBus->dispatch(new Envelope(new SendShipmentTracking($shipmentId), [new DispatchAfterCurrentBusStamp()]));
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send PayPal tracking for shipment #%s: %s', (string) $shipmentId, $exception->getMessage()),
                ['exception' => $exception],
            );
        }
    }
}
