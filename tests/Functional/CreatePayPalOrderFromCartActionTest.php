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
use Sylius\Component\Core\Storage\CartStorageInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
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

    /** @test */
    public function it_records_paypal_as_the_payment_source_when_none_is_given(): void
    {
        $order = $this->seededCart();

        $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $order->getId());

        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $this->assertSame('paypal', $this->recordedPaymentSource($order));
    }

    /** @test */
    public function it_records_venmo_as_the_payment_source_when_venmo_is_enabled_on_the_channel(): void
    {
        $order = $this->seededCart(venmoEnabled: true);

        $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $order->getId() . '?paymentSource=venmo');

        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $this->assertSame('venmo', $this->recordedPaymentSource($order));
    }

    /** @test */
    public function it_rejects_venmo_when_venmo_is_not_enabled_on_the_channel(): void
    {
        $order = $this->seededCart();

        $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $order->getId() . '?paymentSource=venmo');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $this->assertSame(['sylius_paypal.payment_source_not_available'], $this->clientSession()->getFlashBag()->peek('error'));
    }

    /** @test */
    public function it_rejects_a_payment_source_the_cart_placement_does_not_offer(): void
    {
        $order = $this->seededCart(venmoEnabled: true);

        foreach (['card', 'google_pay', 'trustly', 'blik'] as $paymentSource) {
            $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $order->getId() . '?paymentSource=' . $paymentSource);

            $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode(), $paymentSource);
        }
    }

    private function seededCart(bool $venmoEnabled = false): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        if ($venmoEnabled) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $fixtures['paypal_config'];
            $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['venmo_enabled' => true]));
            $this->getEntityManager()->flush();
        }

        $this->seedCurrentCart($order);

        return $order;
    }

    private function recordedPaymentSource(OrderInterface $order): ?string
    {
        $this->getEntityManager()->clear();
        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($order->getId());

        return $order->getLastPayment(PaymentInterface::STATE_CART)?->getDetails()['payment_source'] ?? null;
    }

    private function clientSession(): Session
    {
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getContainer()->get('session.factory');
        /** @var Session $session */
        $session = $sessionFactory->createSession();
        $session->setId((string) $this->client->getCookieJar()->get($session->getName())?->getValue());
        $session->start();

        return $session;
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
