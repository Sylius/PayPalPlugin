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

final class CreatePayPalOrderActionTest extends JsonApiTestCase
{
    /** @test */
    public function it_creates_paypal_order_and_returns_its_data(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        $this->client->request('POST', '/en_US/create-pay-pal-order/TOKEN');

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame($content['orderID'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['status'], 'processing');
    }

    public function test_it_creates_a_paypal_order_for_the_requested_payment_source(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        $this->client->request(
            'POST',
            '/en_US/create-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"paymentSource":"paypal"}',
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function test_it_rejects_a_payment_source_it_does_not_support(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        $this->client->request(
            'POST',
            '/en_US/create-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"paymentSource":"bitcoin"}',
        );

        $this->assertSame(422, $this->client->getResponse()->getStatusCode());
    }
}
