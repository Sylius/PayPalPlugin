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

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;
use Sylius\Bundle\ApiBundle\Command\Checkout\ShipShipment;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiErrorException;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyAddTrackingApi;
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;

final class ShipShipmentThroughAdminApiTest extends JsonApiTestCase
{
    use ShipsShippablePayPalOrderTrait;

    /** @var array<string, object> */
    private array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = $this->loadShippablePayPalOrder();

        /** @var AdminUserInterface $admin */
        $admin = $this->fixtures['admin'];
        $admin->addRole('ROLE_API_ACCESS');
        $this->getEntityManager()->flush();
    }

    protected function tearDown(): void
    {
        $this->resetPayPalDummies();

        parent::tearDown();
    }

    public function test_it_ships_and_sends_the_tracking_with_the_carrier_to_paypal(): void
    {
        $this->ship(['trackingCode' => 'API-TRACK-1', 'carrier' => 'DHL']);

        self::assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $this->tracking()?->getState());
        self::assertSame('DHL', $this->tracking()?->getCarrier());
        self::assertCount(1, DummyAddTrackingApi::$requests);
        self::assertSame('API-TRACK-1', DummyAddTrackingApi::$requests[0]['body']['tracking_number']);
        self::assertSame('DHL', DummyAddTrackingApi::$requests[0]['body']['carrier']);
    }

    public function test_it_sends_the_name_of_an_other_carrier(): void
    {
        $this->ship(['trackingCode' => 'API-TRACK-2', 'carrier' => 'OTHER', 'carrierNameOther' => 'Local Courier']);

        self::assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
        self::assertSame('Local Courier', $this->tracking()?->getCarrierNameOther());
        self::assertSame('Local Courier', DummyAddTrackingApi::$requests[0]['body']['carrier_name_other']);
    }

    public function test_it_ships_a_tracking_code_without_a_carrier_as_before_and_sends_nothing_to_paypal(): void
    {
        $this->ship(['trackingCode' => 'API-TRACK-3']);

        self::assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame('API-TRACK-3', $this->shipment()->getTracking());
        self::assertNull($this->tracking());
        self::assertCount(0, DummyAddTrackingApi::$requests);
    }

    public function test_it_does_not_ship_an_other_carrier_without_a_name(): void
    {
        $this->ship(['trackingCode' => 'API-TRACK-4', 'carrier' => 'OTHER']);

        $this->assertViolation('carrierNameOther', 'Please provide the carrier name when "Other" is selected.');
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
    }

    public function test_it_does_not_ship_with_an_unknown_carrier(): void
    {
        $this->ship(['trackingCode' => 'API-TRACK-5', 'carrier' => 'NOT_A_CARRIER']);

        $this->assertViolation('carrier', 'This carrier is not supported.');
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
    }

    public function test_it_does_not_ship_a_carrier_without_a_tracking_code(): void
    {
        $this->ship(['carrier' => 'DHL']);

        $this->assertViolation('trackingCode', 'Please provide the tracking code when a carrier is selected.');
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
    }

    public function test_it_does_not_ship_a_tracking_code_longer_than_paypal_accepts(): void
    {
        $this->ship(['trackingCode' => str_repeat('A', 65), 'carrier' => 'DHL']);

        $this->assertViolation('trackingCode', 'The tracking code can have at most 64 characters to be sent to PayPal.');
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
    }

    public function test_it_ships_and_leaves_a_retryable_failed_record_when_paypal_errors(): void
    {
        DummyOrderDetailsApi::$failWith = new PayPalApiErrorException('GET v2/checkout/orders/PAYPAL_ORDER_ID', ['name' => 'RESOURCE_NOT_FOUND']);

        $this->ship(['trackingCode' => 'API-TRACK-6', 'carrier' => 'DHL']);

        self::assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        $tracking = $this->tracking();
        self::assertSame(ShipmentTrackingInterface::STATE_FAILED, $tracking?->getState());
        self::assertNotEmpty($tracking->getLastError());
    }

    public function test_it_ships_a_paypal_order_without_a_tracking_code_as_before(): void
    {
        $this->ship([]);

        self::assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertNull($this->tracking());
        self::assertCount(0, DummyAddTrackingApi::$requests);
    }

    public function test_it_ignores_the_carrier_for_an_order_not_paid_with_paypal(): void
    {
        /** @var PaymentMethodInterface $cashOnDelivery */
        $cashOnDelivery = $this->fixtures['cash_on_delivery'];
        $this->order->getLastPayment()?->setMethod($cashOnDelivery);
        $this->getEntityManager()->flush();

        $this->ship(['trackingCode' => 'API-TRACK-7', 'carrier' => 'NOT_A_CARRIER']);

        self::assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame('API-TRACK-7', $this->shipment()->getTracking());
        self::assertNull($this->tracking());
        self::assertCount(0, DummyAddTrackingApi::$requests);
    }

    public function test_it_sends_the_tracking_with_the_carrier_of_an_existing_tracking_record_when_no_carrier_is_sent(): void
    {
        self::getContainer()->get('sylius_paypal.manager.shipment_tracking')->updateCarrier($this->shipment(), 'DHL', null);

        $this->ship(['trackingCode' => 'API-TRACK-10']);

        self::assertSame(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame('DHL', $this->tracking()?->getCarrier());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $this->tracking()?->getState());
        self::assertCount(1, DummyAddTrackingApi::$requests);
    }

    public function test_it_does_not_ship_a_tracking_code_longer_than_paypal_accepts_for_the_carrier_the_shipment_already_has(): void
    {
        self::getContainer()->get('sylius_paypal.manager.shipment_tracking')->updateCarrier($this->shipment(), 'DHL', null);

        $this->ship(['trackingCode' => str_repeat('A', 65)]);

        $this->assertViolation('trackingCode', 'The tracking code can have at most 64 characters to be sent to PayPal.');
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
        self::assertCount(0, DummyAddTrackingApi::$requests);
    }

    public function test_it_sends_the_tracking_when_sylius_ship_shipment_command_ships_a_shipment_that_already_has_a_carrier(): void
    {
        $shipment = $this->shipment();
        self::getContainer()->get('sylius_paypal.manager.shipment_tracking')->updateCarrier($shipment, 'DHL', null);

        self::getContainer()->get('sylius.command_bus')->dispatch(new ShipShipment($shipment->getId(), 'API-TRACK-9'));

        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $this->tracking()?->getState());
    }

    public function test_it_documents_the_carrier_fields(): void
    {
        $this->client->request('GET', '/api/v2/docs', server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('ShipShipmentWithCarrier', $content);
        self::assertStringContainsString('carrierNameOther', $content);
        self::assertStringContainsString('sylius_paypal.tracking.carriers', $content);
    }

    /** @param array<string, string> $body */
    private function ship(array $body): void
    {
        /** @var AdminUserInterface $admin */
        $admin = $this->fixtures['admin'];
        $token = self::getContainer()->get('lexik_jwt_authentication.jwt_manager')->create($admin);

        $this->client->request(
            'PATCH',
            sprintf('/api/v2/admin/shipments/%d/ship', $this->order->getShipments()->first()->getId()),
            server: [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode($body, \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT),
        );
    }

    private function assertViolation(string $propertyPath, string $message): void
    {
        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());

        $violations = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['violations'];
        self::assertContains(['propertyPath' => $propertyPath, 'message' => $message], array_map(
            static fn (array $violation): array => ['propertyPath' => $violation['propertyPath'], 'message' => $violation['message']],
            $violations,
        ));
    }
}
