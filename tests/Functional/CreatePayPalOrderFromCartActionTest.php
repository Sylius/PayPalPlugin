<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;

final class CreatePayPalOrderFromCartActionTest extends JsonApiTestCase
{
    /** @test */
    function it_creates_pay_pal_order_from_cart_and_returns_its_data(): void
    {
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var int $orderId */
        $orderId = $order['new_cart']->getId();

        $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $orderId);

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame($content['id'], $orderId);
        $this->assertSame($content['orderID'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['status'], 'cart');
    }
}
