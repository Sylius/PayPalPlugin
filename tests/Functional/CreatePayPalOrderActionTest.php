<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;

final class CreatePayPalOrderActionTest extends JsonApiTestCase
{
    /** @test */
    function it_creates_pay_pal_order_and_returns_its_data(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        $this->client->request('POST', '/en_US/create-pay-pal-order/TOKEN');

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame($content['orderID'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['status'], 'processing');
    }
}
