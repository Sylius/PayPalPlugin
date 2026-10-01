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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\CommandHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\Command\Checkout\ShipShipment;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Repository\ShipmentRepositoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Command\ShipShipmentWithCarrier;
use Sylius\PayPalPlugin\PackageTracking\CommandHandler\ShipShipmentWithCarrierHandler;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;

final class ShipShipmentWithCarrierHandlerTest extends TestCase
{
    private ShipmentInterface&MockObject $shipment;

    private OrderPayPalPaymentProviderInterface&MockObject $orderPayPalPaymentProvider;

    private ShipmentTrackingManagerInterface&MockObject $shipmentTrackingManager;

    /** @var list<ShipShipment> */
    private array $handled = [];

    private ShipShipmentWithCarrierHandler $handler;

    protected function setUp(): void
    {
        $this->shipment = $this->createMock(ShipmentInterface::class);
        $this->shipment->method('getOrder')->willReturn($this->createMock(OrderInterface::class));

        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $shipmentRepository->method('find')->with(7)->willReturn($this->shipment);

        $this->orderPayPalPaymentProvider = $this->createMock(OrderPayPalPaymentProviderInterface::class);
        $this->shipmentTrackingManager = $this->createMock(ShipmentTrackingManagerInterface::class);

        $this->handler = new ShipShipmentWithCarrierHandler(
            function (ShipShipment $shipShipment): ShipmentInterface {
                $this->handled[] = $shipShipment;

                return $this->shipment;
            },
            $shipmentRepository,
            $this->orderPayPalPaymentProvider,
            $this->shipmentTrackingManager,
        );
    }

    #[Test]
    public function it_saves_the_carrier_of_a_paypal_order_before_shipping(): void
    {
        $this->orderPayPalPaymentProvider->method('provide')->willReturn($this->createMock(PaymentInterface::class));
        $command = new ShipShipmentWithCarrier(7, 'TRACK1', 'OTHER', ' Pigeon Post ');

        $this->shipmentTrackingManager
            ->expects(self::once())
            ->method('updateCarrier')
            ->with($this->shipment, 'OTHER', 'Pigeon Post')
            ->willReturnCallback(fn () => self::assertSame([], $this->handled))
        ;

        self::assertSame($this->shipment, ($this->handler)($command));
        self::assertSame([$command], $this->handled);
    }

    #[Test]
    public function it_ignores_the_carrier_of_an_order_not_paid_with_paypal(): void
    {
        $this->orderPayPalPaymentProvider->method('provide')->willReturn(null);

        $this->shipmentTrackingManager->expects(self::never())->method('updateCarrier');

        ($this->handler)(new ShipShipmentWithCarrier(7, 'TRACK1', 'DHL'));

        self::assertCount(1, $this->handled);
    }

    #[Test]
    public function it_only_ships_when_no_carrier_is_given(): void
    {
        $this->orderPayPalPaymentProvider->expects(self::never())->method('provide');
        $this->shipmentTrackingManager->expects(self::never())->method('updateCarrier');

        ($this->handler)(new ShipShipmentWithCarrier(7, 'TRACK1', ' '));
        ($this->handler)(new ShipShipment(7, 'TRACK1'));

        self::assertCount(2, $this->handled);
    }
}
