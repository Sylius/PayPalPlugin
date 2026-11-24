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

namespace Tests\Sylius\PayPalPlugin\Unit\Factory;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\PayPalPlugin\Factory\PayPalItemFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalItemFactoryTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private RequestStack $requestStack;

    private PayPalItemFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->requestStack = new RequestStack();
        $this->requestStack->push(new Request());
        $this->factory = new PayPalItemFactory($this->urlGenerator, $this->requestStack);
    }

    #[Test]
    public function it_links_the_product_on_the_channel_host_when_there_is_no_request(): void
    {
        $this->requestStack->pop();
        $this->urlGenerator
            ->method('generate')
            ->with('sylius_shop_product_show', ['slug' => 'product-one', '_locale' => 'de_DE'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/de_DE/products/product-one')
        ;

        self::assertSame(
            'https://shop.example.com/de_DE/products/product-one',
            $this->factory->create($this->orderItemInChannel('shop.example.com'), 1, 2000, 0, 'PLN', 'PHYSICAL_GOODS')->toArray()['url'] ?? null,
        );
    }

    #[Test]
    public function it_links_the_product_on_the_request_host_when_there_is_a_request(): void
    {
        $this->urlGenerator
            ->method('generate')
            ->with('sylius_shop_product_show', ['slug' => 'product-one'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://request.example.com/en_US/products/product-one')
        ;

        self::assertSame(
            'https://request.example.com/en_US/products/product-one',
            $this->factory->create($this->orderItemInChannel('shop.example.com'), 1, 2000, 0, 'PLN', 'PHYSICAL_GOODS')->toArray()['url'] ?? null,
        );
    }

    #[Test]
    public function it_links_the_product_on_the_router_host_when_the_channel_has_no_hostname(): void
    {
        $this->requestStack->pop();
        $this->urlGenerator
            ->method('generate')
            ->with('sylius_shop_product_show', ['slug' => 'product-one', '_locale' => 'de_DE'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/de_DE/products/product-one')
        ;

        self::assertSame(
            'http://localhost/de_DE/products/product-one',
            $this->factory->create($this->orderItemInChannel(null), 1, 2000, 0, 'PLN', 'PHYSICAL_GOODS')->toArray()['url'] ?? null,
        );
    }

    #[Test]
    public function it_links_the_product_by_the_slug_of_the_order_locale_when_there_is_no_request(): void
    {
        $this->requestStack->pop();
        $this->urlGenerator
            ->method('generate')
            ->with('sylius_shop_product_show', ['slug' => 'produkt-eins', '_locale' => 'de_DE'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/de_DE/products/produkt-eins')
        ;

        self::assertSame(
            'https://shop.example.com/de_DE/products/produkt-eins',
            $this->factory->create($this->orderItemInChannel('shop.example.com', 'produkt-eins'), 1, 2000, 0, 'PLN', 'PHYSICAL_GOODS')->toArray()['url'] ?? null,
        );
    }

    #[Test]
    public function it_omits_the_url_when_the_product_has_no_translation_in_the_order_locale_and_there_is_no_request(): void
    {
        $this->requestStack->pop();
        $this->urlGenerator->expects(self::never())->method('generate');

        self::assertArrayNotHasKey(
            'url',
            $this->factory->create($this->orderItemInChannel('shop.example.com', null), 1, 2000, 0, 'PLN', 'PHYSICAL_GOODS')->toArray(),
        );
    }

    private function orderItemInChannel(?string $hostname, ?string $orderLocaleSlug = 'product-one'): OrderItemInterface
    {
        $translation = $this->createStub(ProductTranslationInterface::class);
        $translation->method('getSlug')->willReturn($orderLocaleSlug);

        $product = $this->createStub(ProductInterface::class);
        $product->method('getSlug')->willReturn('product-one');
        $product->method('getTranslations')->willReturn(new ArrayCollection(null === $orderLocaleSlug ? [] : ['de_DE' => $translation]));
        $product->method('getTranslation')->willThrowException(new \LogicException('A missing translation must not be created.'));

        $variant = $this->createStub(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);

        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getHostname')->willReturn($hostname);

        $order = $this->createStub(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);
        $order->method('getLocaleCode')->willReturn('de_DE');

        $orderItem = $this->createStub(OrderItemInterface::class);
        $orderItem->method('getProductName')->willReturn('PRODUCT_ONE');
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getOrder')->willReturn($order);

        return $orderItem;
    }

    #[Test]
    public function it_builds_a_bare_item_when_the_order_item_has_no_variant(): void
    {
        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getProductName')->willReturn('PRODUCT_ONE');
        $orderItem->method('getVariant')->willReturn(null);

        self::assertSame([
            'name' => 'PRODUCT_ONE',
            'unit_amount' => [
                'value' => '20.00',
                'currency_code' => 'PLN',
            ],
            'quantity' => 2,
            'tax' => [
                'value' => '2.00',
                'currency_code' => 'PLN',
            ],
            'category' => 'PHYSICAL_GOODS',
        ], $this->factory->create($orderItem, 2, 2000, 200, 'PLN', 'PHYSICAL_GOODS')->toArray());
    }

    #[Test]
    public function it_adds_the_sku_description_and_url_when_they_are_resolvable(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSlug')->willReturn('product-one');
        $product->method('getShortDescription')->willReturn('A very nice product');

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn('SKU_1');
        $variant->method('getProduct')->willReturn($product);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getProductName')->willReturn('PRODUCT_ONE');
        $orderItem->method('getVariant')->willReturn($variant);

        $this->urlGenerator
            ->method('generate')
            ->with('sylius_shop_product_show', ['slug' => 'product-one'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/products/product-one')
        ;

        $item = $this->factory->create($orderItem, 1, 2000, 0, 'PLN', 'DIGITAL_GOODS')->toArray();

        self::assertSame('SKU_1', $item['sku']);
        self::assertSame('A very nice product', $item['description']);
        self::assertSame('http://localhost/products/product-one', $item['url']);
        self::assertSame('DIGITAL_GOODS', $item['category']);
    }

    #[Test]
    public function it_omits_the_sku_description_and_url_it_has_no_data_for(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSlug')->willReturn(null);
        $product->method('getShortDescription')->willReturn(null);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn(null);
        $variant->method('getProduct')->willReturn($product);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getProductName')->willReturn('PRODUCT_ONE');
        $orderItem->method('getVariant')->willReturn($variant);

        $item = $this->factory->create($orderItem, 1, 2000, 0, 'PLN', 'PHYSICAL_GOODS')->toArray();

        self::assertArrayNotHasKey('sku', $item);
        self::assertArrayNotHasKey('description', $item);
        self::assertArrayNotHasKey('url', $item);
    }

    #[Test]
    public function it_truncates_a_product_name_longer_than_127_characters(): void
    {
        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getProductName')->willReturn(str_repeat('a', 200));
        $orderItem->method('getVariant')->willReturn(null);

        $name = $this->factory->create($orderItem, 1, 1000, 0, 'PLN', 'PHYSICAL_GOODS')->toArray()['name'];

        self::assertSame(127, mb_strlen($name));
        self::assertStringEndsWith('...', $name);
    }
}
