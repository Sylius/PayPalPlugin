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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Sylius\PayPalPlugin\Exception\ShippingMethodNotAvailableException;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Model\PayPalShippingOption;
use Sylius\PayPalPlugin\Provider\PayPalShippingCallbackAmountProvider;
use Sylius\PayPalPlugin\Provider\PayPalShippingCallbackAmountProviderInterface;

final class PayPalShippingCallbackAmountProviderTest extends TestCase
{
    private OrderProcessorInterface&MockObject $orderProcessor;

    private ShippingMethodsResolverInterface&MockObject $shippingMethodsResolver;

    private PayPalPurchaseUnitFactoryInterface&MockObject $purchaseUnitFactory;

    private PaymentInterface&MockObject $payment;

    private OrderInterface&MockObject $order;

    private ShipmentInterface&MockObject $shipment;

    private AddressInterface&MockObject $walletAddress;

    private EntityManagerInterface&MockObject $entityManager;

    private int $transactionNestingLevel = 0;

    /** @var list<string> */
    private array $events = [];

    private PayPalShippingCallbackAmountProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->shippingMethodsResolver = $this->createMock(ShippingMethodsResolverInterface::class);
        $this->purchaseUnitFactory = $this->createMock(PayPalPurchaseUnitFactoryInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->shipment = $this->createMock(ShipmentInterface::class);
        $this->walletAddress = $this->createMock(AddressInterface::class);

        $this->payment->method('getOrder')->willReturn($this->order);
        $this->payment->method('getDetails')->willReturn(['reference_id' => 'REFERENCE_ID']);
        $this->order->method('getShipments')->willReturn(new ArrayCollection([$this->shipment]));

        $connection = $this->createMock(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturnCallback(fn (): int => $this->transactionNestingLevel);
        $connection->method('rollBack')->willReturnCallback(function (): bool {
            --$this->transactionNestingLevel;
            $this->events[] = 'rollback';

            return true;
        });

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($connection);
        $this->entityManager->method('beginTransaction')->willReturnCallback(function (): void {
            ++$this->transactionNestingLevel;
            $this->events[] = 'begin';
        });
        $this->entityManager->expects(self::never())->method('commit');

        $this->provider = new PayPalShippingCallbackAmountProvider(
            $this->orderProcessor,
            $this->shippingMethodsResolver,
            $this->purchaseUnitFactory,
            $this->entityManager,
        );
    }

    public function test_it_implements_paypal_shipping_callback_amount_provider_interface(): void
    {
        self::assertInstanceOf(PayPalShippingCallbackAmountProviderInterface::class, $this->provider);
    }

    public function test_it_returns_the_amount_of_the_order_processed_for_the_wallet_address_and_the_selected_method(): void
    {
        $selectedMethod = self::shippingMethod('dhl');
        $this->shippingMethodsResolver->method('getSupportedMethods')->with($this->shipment)->willReturn([self::shippingMethod('ups'), $selectedMethod]);

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

    public function test_it_looks_the_method_up_for_the_wallet_address(): void
    {
        $addressWhenResolved = null;
        $this->order->method('setShippingAddress')->willReturnCallback(function (?AddressInterface $address) use (&$addressWhenResolved): void { $addressWhenResolved = $address; });
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturnCallback(function () use (&$addressWhenResolved): array {
            self::assertSame($this->walletAddress, $addressWhenResolved);

            return [self::shippingMethod('dhl')];
        });
        $this->purchaseUnitFactory->method('create')->willReturn(self::purchaseUnit());

        $this->provider->provide($this->payment, $this->walletAddress, self::selectedOption());
    }

    public function test_it_refuses_a_method_that_is_not_available_for_the_address(): void
    {
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([self::shippingMethod('ups')]);

        $this->shipment->expects(self::never())->method('setMethod');
        $this->orderProcessor->expects(self::never())->method('process');

        $this->expectException(ShippingMethodNotAvailableException::class);

        $this->provider->provide($this->payment, $this->walletAddress, self::selectedOption());
    }

    public function test_it_refuses_an_order_without_a_shipment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShipments')->willReturn(new ArrayCollection());
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $this->orderProcessor->expects(self::never())->method('process');

        $this->expectException(ShippingMethodNotAvailableException::class);

        $this->provider->provide($payment, $this->walletAddress, self::selectedOption());
    }

    public function test_it_rolls_back_everything_the_processing_wrote(): void
    {
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([self::shippingMethod('dhl')]);
        $this->orderProcessor->method('process')->willReturnCallback(function (): void { $this->events[] = 'process'; });
        $this->purchaseUnitFactory->method('create')->willReturn(self::purchaseUnit());

        $this->provider->provide($this->payment, $this->walletAddress, self::selectedOption());

        self::assertSame(['begin', 'process', 'rollback'], $this->events);
        self::assertSame(0, $this->transactionNestingLevel);
    }

    public function test_it_rolls_back_a_transaction_the_processing_left_open(): void
    {
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([self::shippingMethod('dhl')]);
        $this->orderProcessor->method('process')->willReturnCallback(function (): void { ++$this->transactionNestingLevel; });
        $this->purchaseUnitFactory->method('create')->willReturn(self::purchaseUnit());

        $this->provider->provide($this->payment, $this->walletAddress, self::selectedOption());

        self::assertSame(0, $this->transactionNestingLevel);
    }

    public function test_it_rolls_back_when_the_processing_blows_up(): void
    {
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([self::shippingMethod('dhl')]);
        $this->orderProcessor->method('process')->willThrowException(new \RuntimeException('processing failed'));

        try {
            $this->provider->provide($this->payment, $this->walletAddress, self::selectedOption());
            self::fail('The exception should have been rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('processing failed', $exception->getMessage());
        }

        self::assertSame(['begin', 'rollback'], $this->events);
    }

    public function test_it_leaves_a_transaction_opened_before_it_alone(): void
    {
        $this->transactionNestingLevel = 1;
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([self::shippingMethod('dhl')]);
        $this->purchaseUnitFactory->method('create')->willReturn(self::purchaseUnit());

        $this->provider->provide($this->payment, $this->walletAddress, self::selectedOption());

        self::assertSame(1, $this->transactionNestingLevel);
    }

    private static function shippingMethod(string $code): ShippingMethodInterface
    {
        $method = self::createStub(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($code);

        return $method;
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
