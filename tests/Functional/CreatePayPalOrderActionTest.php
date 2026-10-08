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
use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

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

    public function test_it_rejects_venmo_disabled_by_the_merchant_and_keeps_the_live_attempt(): void
    {
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml'])['new_order'];
        $this->createPayPalOrder('paypal');

        $this->createPayPalOrder('venmo');

        $this->assertSame(422, $this->client->getResponse()->getStatusCode());
        $this->assertSame([PaymentInterface::STATE_PROCESSING], $this->paymentStates($order));
        $this->assertSame(
            ['sylius_paypal.payment_source_not_available'],
            $this->client->getRequest()->getSession()->getBag('flashes')->peek('error'),
        );
    }

    public function test_it_creates_a_paypal_order_for_venmo_enabled_by_the_merchant(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $fixtures['paypal_config'];
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['venmo_enabled' => true]));
        $this->getEntityManager()->flush();

        $this->createPayPalOrder('venmo');

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    private function createPayPalOrder(string $paymentSource): void
    {
        $this->client->request(
            'POST',
            '/en_US/create-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: sprintf('{"paymentSource":"%s"}', $paymentSource),
        );
    }

    /** @return list<string> */
    private function paymentStates(OrderInterface $order): array
    {
        $this->getEntityManager()->clear();
        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($order->getId());

        return array_values(array_map(static fn (PaymentInterface $payment): string => (string) $payment->getState(), $order->getPayments()->toArray()));
    }
}
