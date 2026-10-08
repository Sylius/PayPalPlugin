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
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Storage\CartStorageInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class AddToCartActionPaymentSourceTest extends JsonApiTestCase
{
    public function test_it_passes_the_chosen_payment_source_on_to_the_create_order_request(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml']);
        $this->enableVenmo($fixtures['paypal_config']);

        $this->addMugToCart($fixtures['mug'], 'venmo');

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/en_US/create-pay-pal-order-from-cart/\d+\?paymentSource=venmo$#', $location);
    }

    public function test_it_keeps_the_cart_and_tells_the_customer_when_the_payment_source_is_not_available(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $cart */
        $cart = $fixtures['new_cart'];
        $this->seedCurrentCart($cart);
        $orderCount = $this->orderCount();

        $this->addMugToCart($fixtures['mug'], 'venmo');

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        self::assertSame($orderCount, $this->orderCount());

        $session = $this->clientSession();
        self::assertSame($cart->getId(), $session->get('_sylius.cart.WEB'));
        self::assertSame(['sylius_paypal.payment_source_not_available'], $session->getFlashBag()->peek('error'));
    }

    private function addMugToCart(ProductInterface $product, string $paymentSource): void
    {
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/en_US/products/mug');
        $form = $crawler->filter('form[name="sylius_shop_add_to_cart"]')->form();
        $form->setValues(['sylius_shop_add_to_cart[cartItem][variant]' => 'MUG_LOTR']);

        $this->client->request('POST', '/en_US/paypal-add-to-cart/' . $product->getId() . '?paymentSource=' . $paymentSource, $form->getPhpValues());
    }

    private function enableVenmo(GatewayConfigInterface $gatewayConfig): void
    {
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['venmo_enabled' => true]));
        $this->getEntityManager()->flush();
    }

    private function orderCount(): int
    {
        return self::getContainer()->get('sylius.repository.order')->count([]);
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
