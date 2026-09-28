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
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiErrorException;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyAddTrackingApi;
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;

final class ShipShipmentWithPayPalTrackingTest extends JsonApiTestCase
{
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
        $this->ship(['tracking' => 'QA-TRACK-1', 'carrier' => 'DHL']);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/orders/' . $this->order->getId()));
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $this->tracking()?->getState());
        self::assertCount(1, DummyAddTrackingApi::$requests);
    }

    public function test_it_ships_and_records_the_tracking_as_failed_when_paypal_errors(): void
    {
        DummyOrderDetailsApi::$failWith = new PayPalApiErrorException('GET v2/checkout/orders/PAYPAL_ORDER_ID', ['name' => 'RESOURCE_NOT_FOUND']);

        $this->ship(['tracking' => 'QA-TRACK-2', 'carrier' => 'DHL']);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/orders/' . $this->order->getId()));
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        $tracking = $this->tracking();
        self::assertSame(ShipmentTrackingInterface::STATE_FAILED, $tracking?->getState());
        self::assertSame(1, $tracking->getAttempts());
        self::assertNotEmpty($tracking->getLastError());
    }

    public function test_it_does_not_ship_and_explains_why_when_a_tracking_number_has_no_carrier(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-3']);

        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect(), (string) $response->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
        self::assertNull($this->tracking());

        $this->client->followRedirect();
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            'Please select a carrier when a tracking number is provided.',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function test_it_does_not_ship_when_other_carrier_has_no_name(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-4', 'carrier' => 'OTHER']);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/orders/' . $this->order->getId()));
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
        self::assertNull($this->tracking());

        $this->client->followRedirect();
        self::assertStringContainsString(
            'Please provide the carrier name when &quot;Other&quot; is selected.',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function test_it_ships_with_tracking_from_the_shipment_list(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-5', 'carrier' => 'DHL'], '/admin/shipments/');

        // Core's own redirect for this route: the shipment index, carrying the route's `id` as a query parameter.
        self::assertStringStartsWith('/admin/shipments/', (string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $this->tracking()?->getState());
    }

    public function test_it_sends_the_admin_back_to_the_shipment_list_when_a_carrier_is_missing_there(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-6'], '/admin/shipments/');

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/shipments/'));
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
    }

    /** @param array{tracking: string, carrier?: string} $values */
    private function ship(array $values, ?string $page = null): void
    {
        $orderPage = $page ?? '/admin/orders/' . $this->order->getId();
        $crawler = $this->client->request('GET', $orderPage);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form[action$="/ship"]')->form();
        $prefix = $this->formName($form);
        $form[$prefix . '[tracking]'] = $values['tracking'];
        if (isset($values['carrier'])) {
            $form[$prefix . '[paypal_tracking][carrier]'] = $values['carrier'];
        }

        $this->client->submit($form, [], ['HTTP_REFERER' => 'http://localhost' . $orderPage]);
    }

    private function formName(Form $form): string
    {
        foreach (array_keys($form->all()) as $name) {
            if (str_ends_with($name, '[tracking]')) {
                return substr($name, 0, -strlen('[tracking]'));
            }
        }

        self::fail('The ship form has no tracking field.');
    }

    private function shipment(): ShipmentInterface
    {
        $this->getEntityManager()->clear();

        /** @var ShipmentInterface $shipment */
        $shipment = self::getContainer()->get('sylius.repository.shipment')->find($this->order->getShipments()->first()->getId());

        return $shipment;
    }

    private function tracking(): ?ShipmentTrackingInterface
    {
        return self::getContainer()->get('sylius_paypal.repository.shipment_tracking')->findOneByShipment($this->shipment());
    }
}
