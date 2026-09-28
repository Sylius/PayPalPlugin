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
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kept apart from AddToCartActionTest: ApiTestCase shares one kernel per test class, and reloading the
 * translatable product fixtures after a product page request on that kernel fails inside Alice.
 */
final class AddToCartActionPaymentSourceTest extends JsonApiTestCase
{
    public function test_it_passes_the_chosen_payment_source_on_to_the_create_order_request(): void
    {
        /** @var ProductInterface $product */
        $product = $this->loadFixturesFromFiles(['resources/shop.yaml'])['mug'];

        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/en_US/products/mug');
        $form = $crawler->filter('form[name="sylius_shop_add_to_cart"]')->form();
        $form->setValues(['sylius_shop_add_to_cart[cartItem][variant]' => 'MUG_LOTR']);

        $this->client->request('POST', '/en_US/paypal-add-to-cart/' . $product->getId() . '?paymentSource=venmo', $form->getPhpValues());

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/en_US/create-pay-pal-order-from-cart/\d+\?paymentSource=venmo$#', $location);

        // Venmo is not enabled on the fixture's PayPal method, so the forwarded source is refused, not swapped for PayPal.
        $this->client->request('POST', $location);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
    }
}
