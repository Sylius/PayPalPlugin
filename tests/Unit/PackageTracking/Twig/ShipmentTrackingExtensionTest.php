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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Twig;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;
use Sylius\PayPalPlugin\PackageTracking\Twig\ShipmentTrackingExtension;

final class ShipmentTrackingExtensionTest extends TestCase
{
    private OrderPayPalPaymentProviderInterface&MockObject $orderPayPalPaymentProvider;

    private ShipmentTrackingExtension $shipmentTrackingExtension;

    protected function setUp(): void
    {
        parent::setUp();

        $shipmentTrackingRepository = $this->createMock(ShipmentTrackingRepositoryInterface::class);
        $this->orderPayPalPaymentProvider = $this->createMock(OrderPayPalPaymentProviderInterface::class);

        $this->shipmentTrackingExtension = new ShipmentTrackingExtension(
            $shipmentTrackingRepository,
            $this->orderPayPalPaymentProvider,
        );
    }

    #[Test]
    public function it_considers_a_shipment_paid_with_paypal_when_the_order_has_a_paypal_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getOrder')->willReturn($order);

        $this->orderPayPalPaymentProvider->method('provide')->with($order)->willReturn($this->createMock(PaymentInterface::class));

        self::assertTrue($this->shipmentTrackingExtension->isPaidWithPayPal($shipment));
    }

    #[Test]
    public function it_does_not_consider_a_shipment_paid_with_paypal_when_the_order_has_no_paypal_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getOrder')->willReturn($order);

        $this->orderPayPalPaymentProvider->method('provide')->with($order)->willReturn(null);

        self::assertFalse($this->shipmentTrackingExtension->isPaidWithPayPal($shipment));
    }

    #[Test]
    public function it_does_not_consider_a_shipment_without_an_order_paid_with_paypal(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getOrder')->willReturn(null);

        $this->orderPayPalPaymentProvider->expects(self::never())->method('provide');

        self::assertFalse($this->shipmentTrackingExtension->isPaidWithPayPal($shipment));
    }
}
