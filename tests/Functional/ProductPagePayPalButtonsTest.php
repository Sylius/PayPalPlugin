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
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpFoundation\Request;

final class ProductPagePayPalButtonsTest extends JsonApiTestCase
{
    private const TEMPLATES = '@SyliusPayPalPlugin/shop/product/show/content/info/summary/';

    public function test_it_renders_the_paypal_buttons_for_a_product_in_stock(): void
    {
        $product = $this->simpleProduct(onHand: 5);

        self::assertStringContainsString('paypal-web-sdk', $this->render('paypal_checkout', $product));
        self::assertStringContainsString('<venmo-button', $this->render('paypal_venmo', $product));
    }

    public function test_it_renders_no_paypal_buttons_for_a_product_out_of_stock(): void
    {
        $product = $this->simpleProduct(onHand: 0);

        self::assertSame('', trim($this->render('paypal_checkout', $product)));
        self::assertSame('', trim($this->render('paypal_venmo', $product)));
        self::assertSame('', trim($this->render('paypal_messaging', $product)));
    }

    private function simpleProduct(int $onHand): ProductInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);

        $this->getEntityManager()->remove($fixtures['mug_lotr']);

        /** @var ProductVariantInterface $variant */
        $variant = $fixtures['mug_sw'];
        $variant->setTracked(true);
        $variant->setOnHand($onHand);
        $this->getEntityManager()->flush();

        /** @var ProductInterface $product */
        $product = $fixtures['mug'];
        $this->getEntityManager()->refresh($product);

        return $product;
    }

    private function render(string $template, ProductInterface $product): string
    {
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push(Request::create('http://localhost/en_US/products/mug'));

        try {
            return self::getContainer()->get('twig')->render(self::TEMPLATES . $template . '.html.twig', [
                'hookable_metadata' => ['context' => ['product' => $product, 'variant' => $product->getEnabledVariants()->first()]],
            ]);
        } finally {
            $requestStack->pop();
        }
    }
}
