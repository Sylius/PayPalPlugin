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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiErrorException;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\PackageTracking\Twig\Component\ShipmentShipFormComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Tests\Sylius\PayPalPlugin\Service\DummyAddTrackingApi;
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;

final class ShipShipmentWithPayPalTrackingTest extends JsonApiTestCase
{
    use InteractsWithLiveComponents;

    private const FORM_NAME = 'sylius_admin_shipment_ship';

    private const PAYPAL_ORDER_DETAILS = [
        'status' => 'COMPLETED',
        'purchase_units' => [
            [
                'items' => [['name' => 'Mug', 'quantity' => '1', 'sku' => 'MUG_SW']],
                'payments' => ['captures' => [['id' => 'CAPTURE_ID', 'status' => 'COMPLETED']]],
            ],
        ],
    ];

    private OrderInterface $order;

    protected function setUp(): void
    {
        parent::setUp();

        DummyAddTrackingApi::reset();
        DummyOrderDetailsApi::$failWith = null;
        DummyOrderDetailsApi::$response = self::PAYPAL_ORDER_DETAILS;

        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/shippable_paypal_order.yaml']);
        $this->order = $fixtures['shippable_order'];

        /** @var AdminUserInterface $admin */
        $admin = $fixtures['admin'];
        $this->client->loginUser($admin, 'admin');
    }

    protected function tearDown(): void
    {
        DummyAddTrackingApi::reset();
        DummyOrderDetailsApi::$failWith = null;
        DummyOrderDetailsApi::$response = null;

        parent::tearDown();
    }

    public function test_it_ships_and_sends_the_tracking_to_paypal(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-1', 'paypal_tracking' => ['carrier' => 'DHL']]);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/orders/' . $this->order->getId()));
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame('QA-TRACK-1', $this->shipment()->getTracking());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $this->tracking()?->getState());
        self::assertCount(1, DummyAddTrackingApi::$requests);
        self::assertEmailCount(1);
    }

    public function test_it_ships_and_records_the_tracking_as_failed_when_paypal_errors(): void
    {
        DummyOrderDetailsApi::$failWith = new PayPalApiErrorException('GET v2/checkout/orders/PAYPAL_ORDER_ID', ['name' => 'RESOURCE_NOT_FOUND']);

        $this->ship(['tracking' => 'QA-TRACK-2', 'paypal_tracking' => ['carrier' => 'DHL']]);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/orders/' . $this->order->getId()));
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        $tracking = $this->tracking();
        self::assertSame(ShipmentTrackingInterface::STATE_FAILED, $tracking?->getState());
        self::assertSame(1, $tracking->getAttempts());
        self::assertNotEmpty($tracking->getLastError());
    }

    public function test_it_does_not_ship_and_shows_the_error_at_the_carrier_when_a_tracking_number_has_no_carrier(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-3']);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('Please select a carrier when a tracking number is provided.', (string) $response->getContent());
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
        self::assertNull($this->tracking());
    }

    public function test_it_does_not_ship_when_other_carrier_has_no_name(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-4', 'paypal_tracking' => ['carrier' => 'OTHER']]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('Please provide the carrier name when &quot;Other&quot; is selected.', (string) $response->getContent());
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
        self::assertNull($this->tracking());
    }

    public function test_it_saves_nothing_while_the_form_only_re_renders(): void
    {
        $this->shipForm()->submitForm([self::FORM_NAME => ['tracking' => 'QA-TRACK-5', 'paypal_tracking' => ['carrier' => 'DHL']]]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
        self::assertNull($this->shipment()->getTracking());
        self::assertNull($this->tracking());
    }

    public function test_it_ships_with_tracking_from_the_shipment_list(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-6', 'paypal_tracking' => ['carrier' => 'DHL']], ShipmentShipFormComponent::REDIRECT_TO_INDEX);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/shipments/'));
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $this->tracking()?->getState());
    }

    public function test_it_sends_the_tracking_when_a_shipment_with_a_carrier_is_shipped_through_the_admin_api(): void
    {
        $this->shipThroughTheAdminApi('QA-API-1');

        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $this->tracking()?->getState());
    }

    public function test_it_does_not_let_a_paypal_error_escape_a_ship_through_the_admin_api(): void
    {
        DummyOrderDetailsApi::$failWith = new PayPalApiErrorException('GET v2/checkout/orders/PAYPAL_ORDER_ID', ['name' => 'RESOURCE_NOT_FOUND']);

        $this->shipThroughTheAdminApi('QA-API-2');

        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        $tracking = $this->tracking();
        self::assertSame(ShipmentTrackingInterface::STATE_FAILED, $tracking?->getState());
        self::assertSame(1, $tracking->getAttempts());
    }

    private function shipThroughTheAdminApi(string $trackingCode): void
    {
        $shipment = $this->shipment();
        self::getContainer()->get('sylius_paypal.manager.shipment_tracking')->updateCarrier($shipment, 'DHL', null);

        self::getContainer()->get('sylius.command_bus')->dispatch(new ShipShipment($shipment->getId(), $trackingCode));
    }

    /** @param array<string, mixed> $values */
    private function ship(array $values, string $redirectTo = ShipmentShipFormComponent::REDIRECT_TO_ORDER): void
    {
        $this->shipForm()
            ->submitForm([self::FORM_NAME => $values])
            ->call('ship', ['redirectTo' => $redirectTo])
        ;
    }

    private function shipForm(): TestLiveComponent
    {
        $this->client->setServerParameter('HTTP_X_REQUESTED_WITH', 'XMLHttpRequest');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get('request_stack')->push($request);

        $component = $this->createLiveComponent(
            'sylius_paypal_admin:shipment:ship_form',
            [
                'resource' => $this->shipment(),
                'template' => '@SyliusPayPalPlugin/admin/shipment/component/ship.html.twig',
            ],
            $this->client,
        )->setRouteLocale('en_US');
        $component->render();
        $this->client->catchExceptions(true);

        self::getContainer()->get('request_stack')->pop();

        return $component;
    }

    private function shipment(): ShipmentInterface
    {
        self::getContainer()->get('doctrine.orm.entity_manager')->clear();

        /** @var ShipmentInterface $shipment */
        $shipment = self::getContainer()->get('sylius.repository.shipment')->find($this->order->getShipments()->first()->getId());

        return $shipment;
    }

    private function tracking(): ?ShipmentTrackingInterface
    {
        return self::getContainer()->get('sylius_paypal.repository.shipment_tracking')->findOneByShipment($this->shipment());
    }
}
