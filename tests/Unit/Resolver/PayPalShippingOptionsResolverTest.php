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

namespace Tests\Sylius\PayPalPlugin\Unit\Resolver;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Model\ShippingMethodTranslationInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Sylius\PayPalPlugin\Factory\PayPalShippingOptionsFactory;
use Sylius\PayPalPlugin\Resolver\PayPalShippingOptionsResolver;
use Sylius\PayPalPlugin\Resolver\PayPalShippingOptionsResolverInterface;

final class PayPalShippingOptionsResolverTest extends TestCase
{
    private ShippingMethodsResolverInterface&MockObject $shippingMethodsResolver;

    private DelegatingCalculatorInterface&MockObject $shippingCalculator;

    private OrderInterface&MockObject $order;

    private ShipmentInterface&MockObject $shipment;

    private AddressInterface&MockObject $shippingAddress;

    private PayPalShippingOptionsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shippingMethodsResolver = $this->createMock(ShippingMethodsResolverInterface::class);
        $this->shippingCalculator = $this->createMock(DelegatingCalculatorInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->shipment = $this->createMock(ShipmentInterface::class);
        $this->shippingAddress = $this->createMock(AddressInterface::class);

        $this->order->method('getCurrencyCode')->willReturn('USD');
        $this->order->method('getShipments')->willReturn(new ArrayCollection([$this->shipment]));

        $this->resolver = new PayPalShippingOptionsResolver(
            $this->shippingMethodsResolver,
            $this->shippingCalculator,
            new PayPalShippingOptionsFactory(),
        );
    }

    public function test_it_implements_paypal_shipping_options_resolver_interface(): void
    {
        self::assertInstanceOf(PayPalShippingOptionsResolverInterface::class, $this->resolver);
    }

    public function test_it_returns_no_options_when_the_order_has_no_shipment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShipments')->willReturn(new ArrayCollection());

        $this->shippingMethodsResolver->expects(self::never())->method('getSupportedMethods');

        self::assertTrue($this->resolver->resolve($order, $this->shippingAddress)->isEmpty());
    }

    public function test_it_prices_every_method_supported_for_the_given_address(): void
    {
        $this->shippingMethodsResolver
            ->method('getSupportedMethods')
            ->with($this->shipment)
            ->willReturn([$this->shippingMethod('ups', 'UPS'), $this->shippingMethod('dhl', 'DHL')]);
        $this->shippingCalculator->method('calculate')->willReturnOnConsecutiveCalls(1000, 2550);

        $options = $this->resolver->resolve($this->order, $this->shippingAddress);

        self::assertSame([
            ['id' => 'ups', 'amount' => ['currency_code' => 'USD', 'value' => '10.00'], 'type' => 'SHIPPING', 'label' => 'UPS', 'selected' => true],
            ['id' => 'dhl', 'amount' => ['currency_code' => 'USD', 'value' => '25.50'], 'type' => 'SHIPPING', 'label' => 'DHL', 'selected' => false],
        ], $options->toArray());
    }

    public function test_it_keeps_the_shipping_cost_in_minor_units(): void
    {
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$this->shippingMethod('ups', 'UPS')]);
        $this->shippingCalculator->method('calculate')->willReturn(1999);

        $options = $this->resolver->resolve($this->order, $this->shippingAddress);

        self::assertSame(
            ['currency_code' => 'USD', 'value' => '19.99'],
            $options->toArray()[0]['amount'],
        );
    }

    public function test_it_keeps_the_method_the_order_already_carries_selected(): void
    {
        $ups = $this->shippingMethod('ups', 'UPS');
        $dhl = $this->shippingMethod('dhl', 'DHL');

        $this->shipment->method('getMethod')->willReturn($dhl);
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$ups, $dhl]);
        $this->shippingCalculator->method('calculate')->willReturnOnConsecutiveCalls(1000, 2550);

        $options = $this->resolver->resolve($this->order, $this->shippingAddress);

        self::assertSame([false, true], array_column($options->toArray(), 'selected'));
    }

    public function test_it_puts_the_borrowed_address_and_method_back_on_the_order(): void
    {
        $originalAddress = $this->createMock(AddressInterface::class);
        $originalMethod = $this->shippingMethod('fedex', 'FedEx');
        $offeredMethod = $this->shippingMethod('ups', 'UPS');

        $this->order->method('getShippingAddress')->willReturn($originalAddress);
        $this->shipment->method('getMethod')->willReturn($originalMethod);
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$offeredMethod]);
        $this->shippingCalculator->method('calculate')->willReturn(1000);

        $assignedAddresses = [];
        $this->order->method('setShippingAddress')->willReturnCallback(
            function (?AddressInterface $address) use (&$assignedAddresses): void {
                $assignedAddresses[] = $address;
            },
        );

        $assignedMethods = [];
        $this->shipment->method('setMethod')->willReturnCallback(
            function (?ShippingMethodInterface $method) use (&$assignedMethods): void {
                $assignedMethods[] = $method;
            },
        );

        $this->resolver->resolve($this->order, $this->shippingAddress);

        self::assertSame([$this->shippingAddress, $originalAddress], $assignedAddresses);
        self::assertSame([$offeredMethod, $originalMethod], $assignedMethods);
    }

    public function test_it_puts_them_back_even_when_pricing_blows_up(): void
    {
        $originalAddress = $this->createMock(AddressInterface::class);

        $this->order->method('getShippingAddress')->willReturn($originalAddress);
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$this->shippingMethod('ups', 'UPS')]);
        $this->shippingCalculator->method('calculate')->willThrowException(new \RuntimeException('no calculator'));

        $this->order->expects(self::exactly(2))->method('setShippingAddress');

        $this->expectException(\RuntimeException::class);

        $this->resolver->resolve($this->order, $this->shippingAddress);
    }

    public function test_it_labels_the_option_with_the_method_name_in_the_locale_of_the_order(): void
    {
        $this->order->method('getLocaleCode')->willReturn('pl_PL');

        $method = $this->shippingMethod('ups', 'UPS');
        $method
            ->expects(self::once())
            ->method('getTranslation')
            ->with('pl_PL')
            ->willReturn($this->translation('Kurier UPS'))
        ;

        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$method]);
        $this->shippingCalculator->method('calculate')->willReturn(1000);

        $options = $this->resolver->resolve($this->order, $this->shippingAddress);

        self::assertSame('Kurier UPS', $options->toArray()[0]['label']);
    }

    public function test_it_falls_back_to_the_loaded_name_when_the_order_carries_no_locale(): void
    {
        $this->order->method('getLocaleCode')->willReturn(null);

        $method = $this->shippingMethod('ups', 'UPS');
        $method->expects(self::never())->method('getTranslation');

        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$method]);
        $this->shippingCalculator->method('calculate')->willReturn(1000);

        $options = $this->resolver->resolve($this->order, $this->shippingAddress);

        self::assertSame('UPS', $options->toArray()[0]['label']);
    }

    public function test_it_falls_back_to_the_loaded_name_when_that_locale_has_no_translation(): void
    {
        $this->order->method('getLocaleCode')->willReturn('pl_PL');

        $method = $this->shippingMethod('ups', 'UPS');
        $method->method('getTranslation')->with('pl_PL')->willReturn($this->translation(null));

        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$method]);
        $this->shippingCalculator->method('calculate')->willReturn(1000);

        $options = $this->resolver->resolve($this->order, $this->shippingAddress);

        self::assertSame('UPS', $options->toArray()[0]['label']);
    }

    private function shippingMethod(string $code, string $name): ShippingMethodInterface&MockObject
    {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($code);
        $method->method('getName')->willReturn($name);

        return $method;
    }

    private function translation(?string $name): ShippingMethodTranslationInterface&MockObject
    {
        $translation = $this->createMock(ShippingMethodTranslationInterface::class);
        $translation->method('getName')->willReturn($name);

        return $translation;
    }
}
