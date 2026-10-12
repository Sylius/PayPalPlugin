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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Message\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Message\Handler\SendShipmentTrackingHandler;
use Sylius\PayPalPlugin\PackageTracking\Message\SendShipmentTracking;
use Sylius\PayPalPlugin\PackageTracking\Processor\ShipmentTrackingProcessorInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class SendShipmentTrackingHandlerTest extends TestCase
{
    /** @var RepositoryInterface<ShipmentInterface>&MockObject */
    private RepositoryInterface&MockObject $shipmentRepository;

    private ShipmentTrackingProcessorInterface&MockObject $shipmentTrackingProcessor;

    private SendShipmentTrackingHandler $sendShipmentTrackingHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shipmentRepository = $this->createMock(RepositoryInterface::class);
        $this->shipmentTrackingProcessor = $this->createMock(ShipmentTrackingProcessorInterface::class);

        $this->sendShipmentTrackingHandler = new SendShipmentTrackingHandler(
            $this->shipmentRepository,
            $this->shipmentTrackingProcessor,
        );
    }

    #[Test]
    public function it_processes_the_shipment_carried_by_the_message(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);

        $this->shipmentRepository->method('find')->with(42)->willReturn($shipment);

        $this->shipmentTrackingProcessor->expects(self::once())->method('process')->with($shipment);

        ($this->sendShipmentTrackingHandler)(new SendShipmentTracking(42));
    }

    #[Test]
    public function it_does_nothing_when_the_shipment_no_longer_exists(): void
    {
        $this->shipmentRepository->method('find')->with(42)->willReturn(null);

        $this->shipmentTrackingProcessor->expects(self::never())->method('process');

        ($this->sendShipmentTrackingHandler)(new SendShipmentTracking(42));
    }

    #[Test]
    public function it_does_nothing_when_the_found_resource_is_not_a_shipment(): void
    {
        $this->shipmentRepository->method('find')->with(42)->willReturn(new \stdClass());

        $this->shipmentTrackingProcessor->expects(self::never())->method('process');

        ($this->sendShipmentTrackingHandler)(new SendShipmentTracking(42));
    }

    #[Test]
    public function it_lets_a_processing_failure_bubble_up_for_the_messenger_retry(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);

        $this->shipmentRepository->method('find')->with(42)->willReturn($shipment);
        $this->shipmentTrackingProcessor->method('process')->willThrowException(new \RuntimeException('PayPal is down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PayPal is down');

        ($this->sendShipmentTrackingHandler)(new SendShipmentTracking(42));
    }
}
