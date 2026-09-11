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
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromPaymentPageAction;
use Symfony\Component\HttpFoundation\Response;

final class CreatePayPalOrderFromPaymentPageActionTest extends JsonApiTestCase
{
    /** @test */
    public function it_creates_paypal_order_from_payment_page_and_returns_its_data(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);

        $this->client->request('POST', '/en_US/pay-pal-order-payment-page/TOKEN/create');

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame($content['orderId'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['order_id'], 'PAYPAL_ORDER_ID');
    }

    /** @test */
    public function it_returns_not_found_for_a_foreign_or_unknown_token(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);

        $this->client->request('POST', '/en_US/pay-pal-order-payment-page/FOREIGN_TOKEN/create');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /** @test */
    public function it_returns_not_found_for_the_legacy_id_route_when_the_flag_is_disabled(): void
    {
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var int $orderId */
        $orderId = $order['new_cart']->getId();

        $this->client->request('POST', sprintf('/en_US/pay-pal-order-payment-page/%d/create', $orderId));

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /** @test */
    public function it_creates_paypal_order_from_the_legacy_id_route_when_the_flag_is_enabled(): void
    {
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var int $orderId */
        $orderId = $order['new_cart']->getId();
        $this->enableLegacyIdRoutes();

        $this->client->request('POST', sprintf('/en_US/pay-pal-order-payment-page/%d/create', $orderId));

        $response = $this->client->getResponse();
        $content = (array) json_decode((string) $response->getContent(), true);

        $this->assertSame($content['id'], $orderId);
        $this->assertSame($content['orderId'], 'PAYPAL_ORDER_ID');
    }

    private function enableLegacyIdRoutes(): void
    {
        self::getContainer()->set('sylius_paypal.controller.create_paypal_order_from_payment_page', new CreatePayPalOrderFromPaymentPageAction(
            self::getContainer()->get('sylius_abstraction.state_machine'),
            self::getContainer()->get('sylius_paypal.manager.payment_state'),
            self::getContainer()->get('sylius_paypal.provider.order'),
            self::getContainer()->get('sylius_paypal.resolver.capture_payment'),
            true,
        ));
    }
}
