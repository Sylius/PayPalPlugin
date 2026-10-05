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
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;

final class AddToCartActionTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_adds_the_variant_and_quantity_chosen_on_the_product_page(): void
    {
        $product = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml'])['mug'];
        $form = $this->addToCartForm();

        $form->setValues([
            'sylius_shop_add_to_cart[cartItem][variant]' => 'MUG_LOTR',
            'sylius_shop_add_to_cart[cartItem][quantity]' => '3',
        ]);

        $item = $this->addToCart($product, $form);

        self::assertSame('MUG_LOTR', $item->getVariant()->getCode());
        self::assertSame(3, $item->getQuantity());
        self::assertCount(3, $item->getUnits());

        $location = (string) $this->client->getResponse()->headers->get('Location');

        // Follow the real redirect on the same client/session - no session seeding,
        // this is the guest "buy now" flow exactly as the browser performs it. Guards
        // against the cart this action creates ever becoming unresolvable as the
        // caller's own cart on the very next request.
        $this->payPalApi()->mockCreateOrder();
        $this->client->request('POST', $location);

        $response = $this->client->getResponse();
        self::assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $content = (array) json_decode((string) $response->getContent(), true);
        self::assertSame('PAYPAL_ORDER_ID', $content['orderId']);
    }

    public function test_it_returns_validation_errors_as_json_when_the_quantity_is_invalid(): void
    {
        $product = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml'])['mug'];
        $form = $this->addToCartForm();

        $form->setValues([
            'sylius_shop_add_to_cart[cartItem][variant]' => 'MUG_LOTR',
            'sylius_shop_add_to_cart[cartItem][quantity]' => '0',
        ]);

        $this->client->request('POST', '/en_US/paypal-add-to-cart/' . $product->getId(), $form->getPhpValues());

        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame(
            ['errors' => ['Quantity must be between 1 and 9999.']],
            json_decode((string) $response->getContent(), true),
        );
        self::assertSame(1, self::getContainer()->get('sylius.repository.order')->count([]));
    }

    private function addToCartForm(): Form
    {
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/en_US/products/mug');

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        return $crawler->filter('form[name="sylius_shop_add_to_cart"]')->form();
    }

    private function addToCart(ProductInterface $product, Form $form): OrderItemInterface
    {
        $this->client->request('POST', '/en_US/paypal-add-to-cart/' . $product->getId(), $form->getPhpValues());

        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '#/en_US/create-pay-pal-order-from-cart/\d+$#',
            (string) $response->headers->get('Location'),
        );

        /** @var OrderInterface $cart */
        $cart = self::getContainer()->get('sylius.repository.order')->find(
            (int) substr((string) strrchr((string) $response->headers->get('Location'), '/'), 1),
        );

        self::assertCount(1, $cart->getItems());

        return $cart->getItems()->first();
    }
}
