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
use Sylius\Component\Core\Storage\CartStorageInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class CreatePayPalOrderFromPaymentPageActionTest extends JsonApiTestCase
{
    /** @test */
    public function it_creates_paypal_order_from_payment_page_and_returns_its_data(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        $this->seedCurrentCart($order);

        $this->client->request('POST', '/en_US/pay-pal-order-payment-page/' . $order->getId() . '/create');

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame($content['orderId'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['order_id'], 'PAYPAL_ORDER_ID');
    }

    /** @test */
    public function it_cancels_an_abandoned_attempt_and_pays_with_a_fresh_payment(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['abandoned_attempt_cart'];
        $orderId = $order->getId();
        $abandonedPaymentId = $fixtures['abandoned_paypal_payment']->getId();
        $this->seedCurrentCart($order);

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

    /** @test */
    public function it_returns_not_found_when_the_order_does_not_belong_to_the_caller(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        $this->client->request('POST', '/en_US/pay-pal-order-payment-page/' . $order->getId() . '/create');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    private function seedCurrentCart(OrderInterface $order): void
    {
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        self::getContainer()->get('request_stack')->push(new Request());
        self::getContainer()->get('request_stack')->getCurrentRequest()->setSession($session);
        self::getContainer()->get(CartStorageInterface::class)->setForChannel($order->getChannel(), $order);
        $session->save();
        self::getContainer()->get('request_stack')->pop();

        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }
}
