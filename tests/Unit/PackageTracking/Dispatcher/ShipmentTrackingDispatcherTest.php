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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Dispatcher;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Dispatcher\ShipmentTrackingDispatcher;
use Sylius\PayPalPlugin\PackageTracking\Message\SendShipmentTracking;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final class ShipmentTrackingDispatcherTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;

    private LoggerInterface&MockObject $logger;

    private ShipmentTrackingDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->dispatcher = new ShipmentTrackingDispatcher($this->messageBus, $this->logger);
    }

    #[Test]
    public function it_dispatches_a_message_carrying_the_shipment_id(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getId')->willReturn(42);

        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                function (Envelope $envelope): bool {
                    $message = $envelope->getMessage();

                    return $message instanceof SendShipmentTracking && 42 === $message->shipmentId;
                },
            ))
            ->willReturn(new Envelope(new SendShipmentTracking(42)));

        $this->dispatcher->dispatch($shipment);
    }

    #[Test]
    public function it_defers_the_dispatch_until_the_current_bus_is_done(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getId')->willReturn(42);

        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (Envelope $envelope): bool => null !== $envelope->last(DispatchAfterCurrentBusStamp::class),
            ))
            ->willReturn(new Envelope(new SendShipmentTracking(42)))
        ;

        $this->dispatcher->dispatch($shipment);
    }

    #[Test]
    public function it_does_not_let_a_dispatch_failure_break_the_shipping_transition(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getId')->willReturn(42);

        $this->messageBus->method('dispatch')->willThrowException(new \RuntimeException('the broker is down'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(self::stringContains('the broker is down'), self::anything());

        $this->dispatcher->dispatch($shipment);
    }

    #[Test]
    public function it_does_not_dispatch_when_the_shipment_has_no_id(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getId')->willReturn(null);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->dispatcher->dispatch($shipment);
    }
}
