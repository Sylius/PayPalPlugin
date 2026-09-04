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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Sylius\PayPalPlugin\Controller\PayPalOrderShippingCallbackAction;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PayPalOrderShippingCallbackActionTest extends TestCase
{
    private PaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    /** @var AddressFactoryInterface<AddressInterface>&MockObject */
    private AddressFactoryInterface&MockObject $addressFactory;

    private AvailableCountriesProviderInterface&MockObject $availableCountriesProvider;

    private ShippingMethodsResolverInterface&MockObject $shippingMethodsResolver;

    private DelegatingCalculatorInterface&MockObject $shippingCalculator;

    private PayPalOrderShippingCallbackAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paypalPaymentQuery = $this->createMock(PaypalPaymentQueryInterface::class);
        $this->addressFactory = $this->createMock(AddressFactoryInterface::class);
        $this->availableCountriesProvider = $this->createMock(AvailableCountriesProviderInterface::class);
        $this->shippingMethodsResolver = $this->createMock(ShippingMethodsResolverInterface::class);
        $this->shippingCalculator = $this->createMock(DelegatingCalculatorInterface::class);

        $this->action = new PayPalOrderShippingCallbackAction(
            $this->paypalPaymentQuery,
            $this->addressFactory,
            $this->availableCountriesProvider,
            $this->shippingMethodsResolver,
            $this->shippingCalculator,
        );

        $this->availableCountriesProvider->method('provide')->willReturn(['US', 'PL']);
    }

    #[Test]
    public function it_returns_unprocessable_entity_with_country_error_for_an_unsupported_country(): void
    {
        $request = $this->requestWithPayload(['id' => 'PP-ID', 'shipping_address' => ['country_code' => 'FR']]);

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame(
            ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'COUNTRY_ERROR']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function it_returns_unprocessable_entity_with_address_error_when_the_payment_cannot_be_found(): void
    {
        $request = $this->requestWithPayload(['id' => 'PP-ID', 'shipping_address' => ['country_code' => 'US']]);

        $this->paypalPaymentQuery
            ->method('getForUpdateByOrderId')
            ->with('PP-ID')
            ->willThrowException(new PaymentNotFoundException());

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame(
            ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ADDRESS_ERROR']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function it_returns_unprocessable_entity_with_address_error_when_the_order_has_no_shipment(): void
    {
        $request = $this->requestWithPayload(['id' => 'PP-ID', 'shipping_address' => ['country_code' => 'US']]);

        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getShipments')->willReturn(new ArrayCollection());
        $this->paypalPaymentQuery->method('getForUpdateByOrderId')->willReturn($payment);

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_unprocessable_entity_with_address_error_when_no_shipping_method_is_supported(): void
    {
        $request = $this->requestWithPayload(['id' => 'PP-ID', 'shipping_address' => ['country_code' => 'US']]);

        [$payment, $order, $shipment] = $this->paymentWithShipment();
        $this->paypalPaymentQuery->method('getForUpdateByOrderId')->willReturn($payment);
        $this->addressFactory->method('createNew')->willReturn($this->createMock(AddressInterface::class));
        $this->shippingMethodsResolver->method('getSupportedMethods')->with($shipment)->willReturn([]);

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_shipping_options_for_every_supported_method_and_restores_the_original_state(): void
    {
        $request = $this->requestWithPayload([
            'id' => 'PP-ID',
            'shipping_address' => [
                'admin_area_2' => 'Warsaw',
                'admin_area_1' => 'MZ',
                'postal_code' => '00-001',
                'country_code' => 'PL',
            ],
            'purchase_units' => [['reference_id' => 'default', 'amount' => ['currency_code' => 'PLN', 'value' => '30.00']]],
        ]);

        [$payment, $order, $shipment] = $this->paymentWithShipment();
        $originalMethod = $this->createMock(ShippingMethodInterface::class);
        $shipment->method('getMethod')->willReturn($originalMethod);
        $originalShippingAddress = $this->createMock(AddressInterface::class);
        $order->method('getShippingAddress')->willReturn($originalShippingAddress);
        $order->method('getCurrencyCode')->willReturn('PLN');

        $this->paypalPaymentQuery->method('getForUpdateByOrderId')->willReturn($payment);

        $transientAddress = $this->createMock(AddressInterface::class);
        $this->addressFactory->method('createNew')->willReturn($transientAddress);
        $transientAddress->expects(self::once())->method('setCity')->with('Warsaw');
        $transientAddress->expects(self::once())->method('setProvinceCode')->with('MZ');
        $transientAddress->expects(self::once())->method('setPostcode')->with('00-001');
        $transientAddress->expects(self::once())->method('setCountryCode')->with('PL');

        $order->expects(self::exactly(2))->method('setShippingAddress')
            ->willReturnCallback(function (AddressInterface $address) use ($transientAddress, $originalShippingAddress) {
                static $call = 0;
                ++$call;
                if (1 === $call) {
                    self::assertSame($transientAddress, $address);
                } else {
                    self::assertSame($originalShippingAddress, $address);
                }
            });

        $cheapMethod = $this->createMock(ShippingMethodInterface::class);
        $cheapMethod->method('getCode')->willReturn('cheap');
        $cheapMethod->method('getName')->willReturn('Cheap shipping');
        $expressMethod = $this->createMock(ShippingMethodInterface::class);
        $expressMethod->method('getCode')->willReturn('express');
        $expressMethod->method('getName')->willReturn('Express shipping');

        $this->shippingMethodsResolver->method('getSupportedMethods')->with($shipment)->willReturn([$cheapMethod, $expressMethod]);

        $setMethodCalls = [];
        $shipment->expects(self::exactly(3))->method('setMethod')
            ->willReturnCallback(function (?ShippingMethodInterface $method) use (&$setMethodCalls): void {
                $setMethodCalls[] = $method;
            });

        $this->shippingCalculator->method('calculate')->with($shipment)->willReturnOnConsecutiveCalls(500, 1500);

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('PP-ID', $data['id']);
        self::assertSame([
            [
                'id' => 'cheap',
                'amount' => ['currency_code' => 'PLN', 'value' => '5.00'],
                'type' => 'SHIPPING',
                'label' => 'Cheap shipping',
                // $originalMethod matches neither candidate, so the first one is marked selected
                // as a fallback (there must always be exactly one selected option).
                'selected' => true,
            ],
            [
                'id' => 'express',
                'amount' => ['currency_code' => 'PLN', 'value' => '15.00'],
                'type' => 'SHIPPING',
                'label' => 'Express shipping',
                'selected' => false,
            ],
        ], $data['purchase_units'][0]['shipping_options']);
        self::assertSame('30.00', $data['purchase_units'][0]['amount']['value']);
        // the shipment's method is restored to the original one last
        self::assertSame($originalMethod, $setMethodCalls[2]);
    }

    /** @param array<string, mixed> $payload */
    private function requestWithPayload(array $payload): Request
    {
        return Request::create('/pay-pal-order-shipping-callback', 'POST', content: (string) json_encode($payload));
    }

    /** @return array{0: PaymentInterface&MockObject, 1: OrderInterface&MockObject, 2: ShipmentInterface&MockObject} */
    private function paymentWithShipment(): array
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        return [$payment, $order, $shipment];
    }
}
