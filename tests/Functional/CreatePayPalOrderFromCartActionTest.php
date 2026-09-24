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
use Sylius\Component\Core\Storage\CartStorageInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class CreatePayPalOrderFromCartActionTest extends JsonApiTestCase
{
    /** @test */
    public function it_creates_paypal_order_from_cart_and_returns_its_data(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        $orderId = $order->getId();
        $this->seedCurrentCart($order);

        $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $orderId);

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame($content['id'], $orderId);
        $this->assertSame($content['orderId'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['orderID'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['status'], 'cart');
    }

    /** @test */
    public function it_creates_pay_pal_order_from_cart_and_returns_its_data_if_payment_method_is_different_then_pay_pal(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart_with_cash_on_delivery_method.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        $orderId = $order->getId();
        $this->seedCurrentCart($order);

        $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $orderId);

        $response = $this->client->getResponse();
        $content = (array) json_decode($response->getContent(), true);

        $this->assertSame($content['id'], $orderId);
        $this->assertSame($content['orderId'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['orderID'], 'PAYPAL_ORDER_ID');
        $this->assertSame($content['status'], 'cart');
    }

    /** @test */
    public function it_returns_not_found_when_the_order_does_not_belong_to_the_caller(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        // No cart seeded in the session at all - the caller owns nothing.
        $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $order->getId());

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
