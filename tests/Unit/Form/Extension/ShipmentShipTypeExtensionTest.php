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

namespace Tests\Sylius\PayPalPlugin\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Form\Extension\ShipmentShipTypeExtension;
use Sylius\PayPalPlugin\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\Provider\CarrierProvider;
use Sylius\PayPalPlugin\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\Repository\ShipmentTrackingRepositoryInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ShipmentShipTypeExtensionTest extends TestCase
{
    private ShipmentTrackingRepositoryInterface&MockObject $shipmentTrackingRepository;

    private ShipmentTrackingManagerInterface&MockObject $shipmentTrackingManager;

    private OrderPayPalPaymentProviderInterface&MockObject $orderPayPalPaymentProvider;

    private RequestStack $requestStack;

    private ShipmentShipTypeExtension $shipmentShipTypeExtension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shipmentTrackingRepository = $this->createMock(ShipmentTrackingRepositoryInterface::class);
        $this->shipmentTrackingManager = $this->createMock(ShipmentTrackingManagerInterface::class);
        $this->orderPayPalPaymentProvider = $this->createMock(OrderPayPalPaymentProviderInterface::class);
        $this->requestStack = new RequestStack();

        $this->shipmentShipTypeExtension = new ShipmentShipTypeExtension(
            new CarrierProvider(['FEDEX']),
            $this->shipmentTrackingRepository,
            $this->shipmentTrackingManager,
            $this->orderPayPalPaymentProvider,
            $this->createMockForIntersectionOfInterfaces([TranslatorInterface::class, TranslatorBagInterface::class]),
            $this->requestStack,
        );
    }

    #[Test]
    public function it_does_not_require_a_carrier_when_the_order_was_not_paid_with_paypal(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->shipment($order, 'TRACK1');

        $this->orderPayPalPaymentProvider->method('provide')->with($order)->willReturn(null);

        $carrierField = $this->createMock(FormInterface::class);
        $carrierField->expects(self::never())->method('addError');

        $form = $this->form(true, $carrierField, null);

        $this->shipmentTrackingManager->expects(self::never())->method('updateCarrier');

        $this->shipmentShipTypeExtension->validateAndPersistCarrier(new FormEvent($form, $shipment));
    }

    #[Test]
    public function it_requires_a_carrier_when_tracking_is_set_for_a_paypal_paid_order(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->shipment($order, 'TRACK1');

        $this->orderPayPalPaymentProvider->method('provide')->with($order)->willReturn($this->createMock(PaymentInterface::class));

        $carrierField = $this->createMock(FormInterface::class);
        $carrierField->expects(self::once())->method('addError');

        $form = $this->form(true, $carrierField, null);

        $this->shipmentTrackingManager->expects(self::never())->method('updateCarrier');

        $this->shipmentShipTypeExtension->validateAndPersistCarrier(new FormEvent($form, $shipment));
    }

    #[Test]
    public function it_does_not_persist_anything_while_the_live_component_re_renders(): void
    {
        $request = Request::create('/_components/sylius_admin:shipment:ship_form');
        $request->attributes->set('_route', 'ux_live_component');
        $this->requestStack->push($request);

        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->shipment($order, 'TRACK1');

        $this->orderPayPalPaymentProvider->method('provide')->willReturn($this->createMock(PaymentInterface::class));

        $carrierField = $this->createMock(FormInterface::class);
        $carrierField->expects(self::never())->method('addError');

        $form = $this->form(true, $carrierField, 'FEDEX');

        $this->shipmentTrackingManager->expects(self::never())->method('updateCarrier');

        $this->shipmentShipTypeExtension->validateAndPersistCarrier(new FormEvent($form, $shipment));
    }

    #[Test]
    public function it_persists_the_carrier_for_a_paypal_paid_order(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->shipment($order, 'TRACK1');

        $this->orderPayPalPaymentProvider->method('provide')->with($order)->willReturn($this->createMock(PaymentInterface::class));

        $carrierField = $this->createMock(FormInterface::class);
        $carrierField->expects(self::never())->method('addError');

        $form = $this->form(true, $carrierField, 'FEDEX');

        $this->shipmentTrackingManager->expects(self::once())->method('updateCarrier')->with($shipment, 'FEDEX', null);

        $this->shipmentShipTypeExtension->validateAndPersistCarrier(new FormEvent($form, $shipment));
    }

    private function shipment(OrderInterface $order, string $trackingNumber): ShipmentInterface&MockObject
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getOrder')->willReturn($order);
        $shipment->method('getTracking')->willReturn($trackingNumber);

        return $shipment;
    }

    private function form(bool $isValid, FormInterface&MockObject $carrierField, ?string $carrierData): FormInterface&MockObject
    {
        $carrierField->method('getData')->willReturn($carrierData);

        $carrierNameOtherField = $this->createMock(FormInterface::class);
        $carrierNameOtherField->method('getData')->willReturn(null);

        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn($isValid);
        $form->method('get')->willReturnMap([
            ['carrier', $carrierField],
            ['carrier_name_other', $carrierNameOtherField],
        ]);

        return $form;
    }
}
