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
use Sylius\Bundle\ResourceBundle\Controller\AuthorizationCheckerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiErrorException;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\PackageTracking\Twig\Component\ShipmentShipFormComponent;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    use ShipsShippablePayPalOrderTrait;

    private const FORM_NAME = 'sylius_admin_shipment_ship';

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = $this->loadShippablePayPalOrder();

        /** @var AdminUserInterface $admin */
        $admin = $fixtures['admin'];
        $this->client->loginUser($admin, 'admin');
    }

    protected function tearDown(): void
    {
        $this->resetPayPalDummies();

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
        self::assertSame('RESOURCE_NOT_FOUND', $tracking->getLastError());
    }

    public function test_it_ships_a_tracking_number_without_a_carrier_as_before_and_sends_nothing_to_paypal(): void
    {
        $this->ship(['tracking' => 'QA-TRACK-3']);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/orders/' . $this->order->getId()));
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame('QA-TRACK-3', $this->shipment()->getTracking());
        self::assertNull($this->tracking());
        self::assertCount(0, DummyAddTrackingApi::$requests);
    }

    public function test_it_checks_the_permission_before_validating_the_form(): void
    {
        $this->client->disableReboot();
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(false);
        self::getContainer()->set('sylius.resource_controller.authorization_checker', $authorizationChecker);

        $this->ship(['tracking' => 'QA-TRACK-3']);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
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

    public function test_it_does_not_ship_a_carrier_without_a_tracking_code(): void
    {
        $this->ship(['paypal_tracking' => ['carrier' => 'DHL']]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $trackingRow = (new Crawler((string) $response->getContent()))->filter(sprintf('#%s_tracking', self::FORM_NAME))->closest('.col-12');
        self::assertStringContainsString('Please provide the tracking code when a carrier is selected.', $trackingRow?->text() ?? '');
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
        self::assertNull($this->tracking());
    }

    public function test_it_ships_a_tracking_number_through_the_sylius_ship_route_as_before(): void
    {
        $this->shipThroughSyliusRoute(['tracking' => 'QA-TRACK-7']);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/shipments/?id=' . $this->shipment()->getId()));
        self::assertSame(ShipmentInterface::STATE_SHIPPED, $this->shipment()->getState());
        self::assertSame('QA-TRACK-7', $this->shipment()->getTracking());
        self::assertNull($this->tracking());
        self::assertCount(0, DummyAddTrackingApi::$requests);
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

    public function test_it_does_not_ship_when_a_pre_ship_listener_stops_it(): void
    {
        $this->onPreShip(static function (ResourceControllerEvent $event): void {
            $event->stop('sylius.resource.update_error');
        });

        $this->ship(['tracking' => 'QA-TRACK-7', 'paypal_tracking' => ['carrier' => 'DHL']]);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/orders/' . $this->order->getId()));
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
        self::assertNull($this->tracking());
        self::assertCount(0, DummyAddTrackingApi::$requests);
    }

    public function test_it_answers_with_the_response_a_pre_ship_listener_sets(): void
    {
        $this->onPreShip(static function (ResourceControllerEvent $event): void {
            $event->stop('sylius.resource.update_error');
            $event->setResponse(new RedirectResponse('/admin/custom'));
        });

        $this->ship(['tracking' => 'QA-TRACK-8', 'paypal_tracking' => ['carrier' => 'DHL']]);

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/custom'));
        self::assertSame(ShipmentInterface::STATE_READY, $this->shipment()->getState());
    }

    public function test_it_tells_the_shipment_was_shipped_in_the_meantime_instead_of_shipping_it_again(): void
    {
        $form = $this->shipForm();
        $this->shipElsewhere();

        $form->call('ship');

        self::assertTrue($this->client->getResponse()->isRedirect('/admin/orders/' . $this->order->getId()));
        self::assertSame(0, self::getContainer()->get('doctrine.dbal.default_connection')->getTransactionNestingLevel());
        self::assertSame('SHIPPED-ELSEWHERE', $this->shipment()->getTracking());

        $this->client->followRedirect();
        self::assertStringContainsString('This shipment can no longer be shipped. It may have been shipped in the meantime.', (string) $this->client->getResponse()->getContent());
    }

    public function test_it_tells_the_shipment_was_shipped_in_the_meantime_when_the_form_re_renders(): void
    {
        $form = $this->shipForm();
        $this->shipElsewhere();

        $form->submitForm([self::FORM_NAME => ['paypal_tracking' => ['carrier' => 'DHL']]]);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $root = (new Crawler((string) $response->getContent()))->filter('[data-controller~="live"]');
        self::assertCount(1, $root);
        self::assertStringContainsString('This shipment can no longer be shipped. Refresh the page to see its current state.', $root->text());
    }

    public function test_it_renders_nothing_for_a_shipment_already_shipped_when_mounted(): void
    {
        $this->shipElsewhere();

        self::assertSame('', trim((string) $this->shipForm()->render()));
    }

    /** @param array<string, mixed> $values */
    private function ship(array $values, string $redirectTo = ShipmentShipFormComponent::REDIRECT_TO_ORDER): void
    {
        $this->shipForm()
            ->submitForm([self::FORM_NAME => $values])
            ->call('ship', ['redirectTo' => $redirectTo])
        ;
    }

    /** @param array<string, mixed> $values */
    private function shipThroughSyliusRoute(array $values): void
    {
        $crawler = $this->client->request('GET', '/admin/orders/' . $this->order->getId());
        $values['_token'] = $crawler->filter(sprintf('input[name="%s[_token]"]', self::FORM_NAME))->attr('value');

        $this->client->request('PUT', sprintf('/admin/shipments/%d/ship', $this->shipment()->getId()), [self::FORM_NAME => $values]);
    }

    private function shipElsewhere(): void
    {
        self::getContainer()->get('sylius.command_bus')->dispatch(new ShipShipment($this->shipment()->getId(), 'SHIPPED-ELSEWHERE'));
    }

    private function onPreShip(callable $listener): void
    {
        $this->client->disableReboot();
        self::getContainer()->get('event_dispatcher')->addListener('sylius.shipment.pre_ship', $listener);
    }

    private function shipForm(): TestLiveComponent
    {
        $this->client->disableReboot();
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
}
