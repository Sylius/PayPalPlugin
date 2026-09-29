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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Model\PayPalShippingOption;
use Sylius\PayPalPlugin\Provider\PayPalShippingCallbackAmountProvider;
use Sylius\PayPalPlugin\Provider\PayPalShippingCallbackAmountProviderInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class PayPalShippingCallbackAmountProviderTest extends TestCase
{
    private OrderProcessorInterface&MockObject $orderProcessor;

    private RepositoryInterface&MockObject $shippingMethodRepository;

    private PayPalPurchaseUnitFactoryInterface&MockObject $purchaseUnitFactory;

    private PaymentInterface&MockObject $payment;

    private OrderInterface&MockObject $order;

    private ShipmentInterface&MockObject $shipment;

    private AddressInterface&MockObject $walletAddress;

    private PayPalShippingCallbackAmountProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->shippingMethodRepository = $this->createMock(RepositoryInterface::class);
        $this->purchaseUnitFactory = $this->createMock(PayPalPurchaseUnitFactoryInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->shipment = $this->createMock(ShipmentInterface::class);
        $this->walletAddress = $this->createMock(AddressInterface::class);

        $this->payment->method('getOrder')->willReturn($this->order);
        $this->payment->method('getDetails')->willReturn(['reference_id' => 'REFERENCE_ID']);
        $this->order->method('getShipments')->willReturn(new ArrayCollection([$this->shipment]));

        $this->provider = new PayPalShippingCallbackAmountProvider(
            $this->orderProcessor,
            $this->shippingMethodRepository,
            $this->purchaseUnitFactory,
        );
    }

    public function test_it_implements_paypal_shipping_callback_amount_provider_interface(): void
    {
        self::assertInstanceOf(PayPalShippingCallbackAmountProviderInterface::class, $this->provider);
    }

    public function test_it_returns_the_amount_of_the_order_processed_for_the_wallet_address_and_the_selected_method(): void
    {
        $selectedMethod = $this->createMock(ShippingMethodInterface::class);
        $this->shippingMethodRepository->method('findOneBy')->with(['code' => 'dhl'])->willReturn($selectedMethod);

        $state = [];
        $this->order->method('setShippingAddress')->willReturnCallback(function (?AddressInterface $address) use (&$state): void { $state['shipping'] = $address; });
        $this->order->method('setBillingAddress')->willReturnCallback(function (?AddressInterface $address) use (&$state): void { $state['billing'] = $address; });
        $this->shipment->method('setMethod')->willReturnCallback(function (?ShippingMethodInterface $method) use (&$state): void { $state['method'] = $method; });

        $processedWith = [];
        $this->orderProcessor->method('process')->willReturnCallback(function () use (&$state, &$processedWith): void { $processedWith[] = $state; });

        $this->purchaseUnitFactory->expects(self::once())->method('create')->with($this->payment, 'REFERENCE_ID')->willReturn(self::purchaseUnit());

        $amount = $this->provider->provide($this->payment, $this->walletAddress, self::selectedOption());

        self::assertSame(['shipping' => $this->walletAddress, 'billing' => $this->walletAddress, 'method' => $selectedMethod], $processedWith[0]);
        self::assertSame('64.28', $amount['value']);
        self::assertSame('3.70', $amount['breakdown']['tax_total']['value']);
    }

    public function test_it_keeps_the_method_of_the_order_when_the_selected_option_matches_no_method(): void
    {
        $this->shippingMethodRepository->method('findOneBy')->willReturn(null);
        $this->purchaseUnitFactory->method('create')->willReturn(self::purchaseUnit());

        $this->shipment->expects(self::never())->method('setMethod');

        $this->provider->provide($this->payment, $this->walletAddress, self::selectedOption());
    }

    private static function selectedOption(): PayPalShippingOption
    {
        return new PayPalShippingOption('dhl', 'DHL', 'USD', 772, true);
    }

    private static function purchaseUnit(): PayPalPurchaseUnit
    {
        return new PayPalPurchaseUnit(
            'REFERENCE_ID',
            'INVOICE_ID',
            'USD',
            6428,
            772,
            52.86,
            3.70,
            0,
            'MERCHANT_ID',
            [],
            true,
        );
    }
}
