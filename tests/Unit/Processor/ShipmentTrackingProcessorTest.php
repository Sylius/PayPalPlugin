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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Api\AddTrackingApiInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Entity\ShipmentTracking;
use Sylius\PayPalPlugin\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\Exception\ShipmentTrackingNotReadyException;
use Sylius\PayPalPlugin\Processor\ShipmentTrackingProcessor;
use Sylius\PayPalPlugin\Provider\CarrierProvider;
use Sylius\PayPalPlugin\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\Provider\ShipmentTrackingItemsProviderInterface;
use Sylius\PayPalPlugin\Repository\ShipmentTrackingRepositoryInterface;

final class ShipmentTrackingProcessorTest extends TestCase
{
    private ShipmentTrackingRepositoryInterface&MockObject $repository;

    private OrderPayPalPaymentProviderInterface&MockObject $paymentProvider;

    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private AddTrackingApiInterface&MockObject $addTrackingApi;

    private ShipmentTrackingItemsProviderInterface&MockObject $itemsProvider;

    private EntityManagerInterface&MockObject $entityManager;

    private ShipmentTrackingProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(ShipmentTrackingRepositoryInterface::class);
        $this->paymentProvider = $this->createMock(OrderPayPalPaymentProviderInterface::class);
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->addTrackingApi = $this->createMock(AddTrackingApiInterface::class);
        $this->itemsProvider = $this->createMock(ShipmentTrackingItemsProviderInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->processor = new ShipmentTrackingProcessor(
            $this->repository,
            $this->paymentProvider,
            $this->authorizeClientApi,
            $this->orderDetailsApi,
            $this->addTrackingApi,
            $this->itemsProvider,
            new CarrierProvider(['FEDEX']),
            $this->entityManager,
            $this->createMock(LoggerInterface::class),
        );
    }

    #[Test]
    public function it_sends_tracking_and_marks_the_record_as_synced(): void
    {
        $shipment = $this->shipment('TRACK1');
        $tracking = $this->trackingFor($shipment, 'FEDEX');

        $this->repository->method('findOneByShipment')->with($shipment)->willReturn($tracking);
        $payment = $this->payPalPayment(['paypal_order_id' => 'ORDER123', 'transaction_id' => 'CAP123']);
        $this->paymentProvider->method('provide')->willReturn($payment);
        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');
        $this->orderDetailsApi->method('get')->with('TOKEN', 'ORDER123')->willReturn(['status' => 'COMPLETED']);
        $this->itemsProvider->method('provide')->with($shipment)->willReturn([['name' => 'T-Shirt', 'quantity' => '1', 'sku' => 'sku01']]);

        $this->addTrackingApi
            ->expects(self::once())
            ->method('add')
            ->with('TOKEN', 'ORDER123', [
                'capture_id' => 'CAP123',
                'tracking_number' => 'TRACK1',
                'carrier' => 'FEDEX',
                'notify_payer' => true,
                'items' => [['name' => 'T-Shirt', 'quantity' => '1', 'sku' => 'sku01']],
            ])
            ->willReturn([
                'purchase_units' => [[
                    'shipping' => ['trackers' => [['id' => 'TRK-TRACK1-1AS', 'status' => 'SHIPPED']]],
                ]],
            ]);

        $this->entityManager->expects(self::once())->method('flush');

        $this->processor->process($shipment);

        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $tracking->getState());
        self::assertSame('TRK-TRACK1-1AS', $tracking->getPayPalTrackerId());
    }

    #[Test]
    public function it_includes_the_free_text_carrier_name_for_the_other_carrier(): void
    {
        $shipment = $this->shipment('TRACK1');
        $tracking = $this->trackingFor($shipment, 'OTHER');
        $tracking->setCarrierNameOther('Local Courier');

        $this->repository->method('findOneByShipment')->willReturn($tracking);
        $this->paymentProvider->method('provide')->willReturn($this->payPalPayment(['paypal_order_id' => 'ORDER123', 'transaction_id' => 'CAP123']));
        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');
        $this->orderDetailsApi->method('get')->willReturn(['status' => 'COMPLETED']);
        $this->itemsProvider->method('provide')->willReturn([]);

        $this->addTrackingApi
            ->expects(self::once())
            ->method('add')
            ->with('TOKEN', 'ORDER123', self::callback(
                fn (array $body): bool => 'OTHER' === ($body['carrier'] ?? null) && 'Local Courier' === ($body['carrier_name_other'] ?? null),
            ))
            ->willReturn([]);

        $this->processor->process($shipment);

        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $tracking->getState());
    }

    #[Test]
    public function it_records_the_failure_and_rethrows_when_paypal_errors_so_the_message_can_be_retried(): void
    {
        $shipment = $this->shipment('TRACK1');
        $tracking = $this->trackingFor($shipment, 'FEDEX');

        $this->repository->method('findOneByShipment')->willReturn($tracking);
        $this->paymentProvider->method('provide')->willReturn($this->payPalPayment(['paypal_order_id' => 'ORDER123', 'transaction_id' => 'CAP123']));
        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');
        $this->orderDetailsApi->method('get')->willReturn(['status' => 'COMPLETED']);
        $this->itemsProvider->method('provide')->willReturn([]);
        $this->addTrackingApi->method('add')->willThrowException(new \RuntimeException('PayPal is down'));

        $this->entityManager->expects(self::once())->method('flush');

        try {
            $this->processor->process($shipment);

            self::fail('Expected the PayPal failure to be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('PayPal is down', $exception->getMessage());
        }

        self::assertSame(ShipmentTrackingInterface::STATE_FAILED, $tracking->getState());
        self::assertStringContainsString('PayPal is down', (string) $tracking->getLastError());
    }

    #[Test]
    public function it_does_not_call_paypal_when_the_order_status_is_not_eligible(): void
    {
        $shipment = $this->shipment('TRACK1');
        $tracking = $this->trackingFor($shipment, 'FEDEX');

        $this->repository->method('findOneByShipment')->willReturn($tracking);
        $this->paymentProvider->method('provide')->willReturn($this->payPalPayment(['paypal_order_id' => 'ORDER123', 'transaction_id' => 'CAP123']));
        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');
        $this->orderDetailsApi->method('get')->willReturn(['status' => 'CREATED']);

        $this->addTrackingApi->expects(self::never())->method('add');

        $this->processor->process($shipment);

        self::assertSame(ShipmentTrackingInterface::STATE_FAILED, $tracking->getState());
        self::assertStringContainsString('CREATED', (string) $tracking->getLastError());
    }

    #[Test]
    public function it_throws_instead_of_failing_permanently_when_the_shipment_is_not_shipped_yet(): void
    {
        $shipment = $this->shipment('TRACK1', ShipmentInterface::STATE_READY);
        $tracking = $this->trackingFor($shipment, 'FEDEX');

        $this->repository->method('findOneByShipment')->willReturn($tracking);
        $this->paymentProvider->method('provide')->willReturn($this->payPalPayment(['paypal_order_id' => 'ORDER123']));

        $this->addTrackingApi->expects(self::never())->method('add');
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(ShipmentTrackingNotReadyException::class);

        $this->processor->process($shipment);
    }

    #[Test]
    public function it_does_nothing_when_the_shipment_has_no_tracking_record(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $this->repository->method('findOneByShipment')->willReturn(null);

        $this->paymentProvider->expects(self::never())->method('provide');
        $this->addTrackingApi->expects(self::never())->method('add');
        $this->entityManager->expects(self::never())->method('flush');

        $this->processor->process($shipment);
    }

    private function shipment(string $trackingNumber, string $state = ShipmentInterface::STATE_SHIPPED): ShipmentInterface&MockObject
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getId')->willReturn(1);
        $shipment->method('getTracking')->willReturn($trackingNumber);
        $shipment->method('getState')->willReturn($state);
        $shipment->method('getOrder')->willReturn($this->createMock(OrderInterface::class));

        return $shipment;
    }

    private function trackingFor(ShipmentInterface $shipment, string $carrier): ShipmentTracking
    {
        $tracking = new ShipmentTracking($shipment);
        $tracking->setCarrier($carrier);

        return $tracking;
    }

    /** @param array<string, mixed> $details */
    private function payPalPayment(array $details): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($details);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));

        return $payment;
    }
}
