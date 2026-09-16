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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class CreatePayPalOrderFromPaymentPageActionTest extends JsonApiTestCase
{
    /** @test */
    public function it_creates_paypal_order_from_payment_page_and_returns_its_data(): void
    {
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var int $orderId */
        $orderId = $order['new_cart']->getId();

        $this->client->request('POST', '/en_US/pay-pal-order-payment-page/' . $orderId . '/create');

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame($content['orderId'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['order_id'], 'PAYPAL_ORDER_ID');
    }

    /** @test */
    public function it_cancels_an_abandoned_attempt_and_pays_with_a_fresh_payment(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_cart.yaml']);
        /** @var int $orderId */
        $orderId = $fixtures['abandoned_attempt_cart']->getId();
        $abandonedPaymentId = $fixtures['abandoned_paypal_payment']->getId();

        $this->client->request('POST', '/en_US/pay-pal-order-payment-page/' . $orderId . '/create');

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('PAYPAL_ORDER_ID', $content['orderId']);

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        $abandonedPayment = $order->getLastPayment(PaymentInterface::STATE_CANCELLED);
        $this->assertNotNull($abandonedPayment);
        $this->assertSame($abandonedPaymentId, $abandonedPayment->getId());

        $newPayment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        $this->assertNotNull($newPayment);
        $this->assertSame('PAYPAL', $newPayment->getMethod()->getCode());
        $this->assertSame($order->getTotal(), $newPayment->getAmount());
    }
}
