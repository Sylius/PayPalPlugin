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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\PayPalPlugin\Factory\PayPalItemFactory;
use Sylius\PayPalPlugin\Model\PayPalBillingCycle;
use Sylius\PayPalPlugin\Model\PayPalBillingCycleTenure;
use Sylius\PayPalPlugin\Model\PayPalBillingIntervalUnit;
use Sylius\PayPalPlugin\Model\PayPalBillingPlan;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Provider\OrderItemNonNeutralTaxesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalItemDataProviderTest extends TestCase
{
    private OrderItemNonNeutralTaxesProviderInterface&MockObject $orderItemNonNeutralTaxesProvider;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    private PayPalItemDataProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderItemNonNeutralTaxesProvider = $this->createMock(OrderItemNonNeutralTaxesProviderInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->provider = new PayPalItemDataProvider(
            $this->orderItemNonNeutralTaxesProvider,
            new PayPalItemFactory($this->urlGenerator),
        );
    }

    #[Test]
    public function returns_array_of_items_with_tax(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('isShippingRequired')->willReturn(true);
        $orderItem->method('getProductName')->willReturn('PRODUCT_ONE');
        $order->method('getCurrencyCode')->willReturn('PLN');

        $orderItem->method('getUnitPrice')->willReturn(2000);
        $orderItem->method('getQuantity')->willReturn(1);

        $this->orderItemNonNeutralTaxesProvider->method('provide')->with($orderItem)->willReturn([200]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '20.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '2.00',
                        'currency_code' => 'PLN',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
            ],
            'total_item_value' => '20.00',
            'total_tax' => '2.00',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function returns_items_with_sku_description_and_url(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $product = $this->createMock(ProductInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getCurrencyCode')->willReturn('PLN');

        $orderItem->method('getProductName')->willReturn('PRODUCT_ONE');
        $orderItem->method('getUnitPrice')->willReturn(2000);
        $orderItem->method('getQuantity')->willReturn(1);
        $orderItem->method('getVariant')->willReturn($variant);

        $variant->method('getCode')->willReturn('SKU_1');
        $variant->method('getProduct')->willReturn($product);
        $product->method('getSlug')->willReturn('product-one');
        $product->method('getShortDescription')->willReturn('A very nice product');

        $this->urlGenerator
            ->method('generate')
            ->with('sylius_shop_product_show', ['slug' => 'product-one'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/products/product-one');

        $this->orderItemNonNeutralTaxesProvider->method('provide')->with($orderItem)->willReturn([0]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '20.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                    'sku' => 'SKU_1',
                    'description' => 'A very nice product',
                    'url' => 'http://localhost/products/product-one',
                ],
            ],
            'total_item_value' => '20.00',
            'total_tax' => '0.00',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function marks_items_as_digital_goods_when_shipping_is_not_required(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('isShippingRequired')->willReturn(false);
        $order->method('getCurrencyCode')->willReturn('PLN');

        $orderItem->method('getProductName')->willReturn('DIGITAL_PRODUCT');
        $orderItem->method('getUnitPrice')->willReturn(2000);
        $orderItem->method('getQuantity')->willReturn(1);

        $this->orderItemNonNeutralTaxesProvider->method('provide')->with($orderItem)->willReturn([0]);

        $result = $this->provider->provide($order);

        self::assertSame('DIGITAL_GOODS', $result['items'][0]['category']);
    }

    #[Test]
    public function returns_array_of_items_with_different_quantities_with_tax(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('isShippingRequired')->willReturn(true);
        $orderItem->method('getProductName')->willReturn('PRODUCT_ONE');
        $order->method('getCurrencyCode')->willReturn('PLN');

        $orderItem->method('getUnitPrice')->willReturn(2000);
        $orderItem->method('getQuantity')->willReturn(3);

        $this->orderItemNonNeutralTaxesProvider->method('provide')->with($orderItem)->willReturn([200, 200, 200]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '20.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 3,
                    'tax' => [
                        'value' => '2.00',
                        'currency_code' => 'PLN',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
            ],
            'total_item_value' => '60.00',
            'total_tax' => '6.00',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function returns_array_of_items_with_different_quantities_without_tax(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('isShippingRequired')->willReturn(true);
        $orderItem->method('getProductName')->willReturn('PRODUCT_ONE');
        $order->method('getCurrencyCode')->willReturn('PLN');

        $orderItem->method('getUnitPrice')->willReturn(2000);
        $orderItem->method('getQuantity')->willReturn(3);

        $this->orderItemNonNeutralTaxesProvider->method('provide')->with($orderItem)->willReturn([0]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '20.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 3,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
            ],
            'total_item_value' => '60.00',
            'total_tax' => '0.00',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function returns_array_of_different_items_with_different_quantities_without_tax(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItemOne = $this->createMock(OrderItemInterface::class);
        $orderItemTwo = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItemOne, $orderItemTwo]));
        $order->method('isShippingRequired')->willReturn(true);
        $orderItemOne->method('getProductName')->willReturn('PRODUCT_ONE');
        $orderItemOne->method('getUnitPrice')->willReturn(2000);
        $orderItemOne->method('getQuantity')->willReturn(3);

        $orderItemTwo->method('getProductName')->willReturn('PRODUCT_TWO');
        $orderItemTwo->method('getUnitPrice')->willReturn(1000);
        $orderItemTwo->method('getQuantity')->willReturn(2);

        $order->method('getCurrencyCode')->willReturn('PLN');

        $this->orderItemNonNeutralTaxesProvider->method('provide')
            ->willReturnMap([
                [$orderItemOne, [0]],
                [$orderItemTwo, [0]],
            ]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '20.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 3,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
                [
                    'name' => 'PRODUCT_TWO',
                    'unit_amount' => [
                        'value' => '10.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 2,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
            ],
            'total_item_value' => '80.00',
            'total_tax' => '0.00',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function returns_array_of_different_items_with_different_quantities_with_tax(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItemOne = $this->createMock(OrderItemInterface::class);
        $orderItemTwo = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItemOne, $orderItemTwo]));
        $order->method('isShippingRequired')->willReturn(true);
        $orderItemOne->method('getProductName')->willReturn('PRODUCT_ONE');
        $orderItemOne->method('getUnitPrice')->willReturn(2000);
        $orderItemOne->method('getQuantity')->willReturn(3);

        $orderItemTwo->method('getProductName')->willReturn('PRODUCT_TWO');
        $orderItemTwo->method('getUnitPrice')->willReturn(1000);
        $orderItemTwo->method('getQuantity')->willReturn(2);

        $order->method('getCurrencyCode')->willReturn('PLN');

        $this->orderItemNonNeutralTaxesProvider->method('provide')
            ->willReturnMap([
                [$orderItemOne, [100, 100, 100]],
                [$orderItemTwo, [200, 100]],
            ]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '20.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 3,
                    'tax' => [
                        'value' => '1.00',
                        'currency_code' => 'PLN',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
                [
                    'name' => 'PRODUCT_TWO',
                    'unit_amount' => [
                        'value' => '10.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 2,
                    'tax' => [
                        'value' => '1.50',
                        'currency_code' => 'PLN',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
            ],
            'total_item_value' => '80.00',
            'total_tax' => '6.00',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function splits_items_when_tax_is_not_evenly_divisible(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('isShippingRequired')->willReturn(true);
        $orderItem->method('getProductName')->willReturn('PRODUCT_WITH_NON_DIVISIBLE_TAX');
        $order->method('getCurrencyCode')->willReturn('USD');

        $orderItem->method('getUnitPrice')->willReturn(1500);
        $orderItem->method('getQuantity')->willReturn(3);

        $this->orderItemNonNeutralTaxesProvider->method('provide')->with($orderItem)->willReturn([166, 167, 167]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT_WITH_NON_DIVISIBLE_TAX',
                    'unit_amount' => [
                        'value' => '15.00',
                        'currency_code' => 'USD',
                    ],
                    'quantity' => 2,
                    'tax' => [
                        'value' => '1.66',
                        'currency_code' => 'USD',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
                [
                    'name' => 'PRODUCT_WITH_NON_DIVISIBLE_TAX',
                    'unit_amount' => [
                        'value' => '15.00',
                        'currency_code' => 'USD',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '1.68',
                        'currency_code' => 'USD',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
            ],
            'total_item_value' => '45.00',
            'total_tax' => '5.00',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function handles_complex_non_divisible_tax_scenario_with_multiple_items(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItemOne = $this->createMock(OrderItemInterface::class);
        $orderItemTwo = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([
            $orderItemOne,
            $orderItemTwo,
        ]));
        $order->method('isShippingRequired')->willReturn(true);

        $orderItemOne->method('getProductName')->willReturn('PRODUCT_ONE');
        $orderItemOne->method('getUnitPrice')->willReturn(2000);
        $orderItemOne->method('getQuantity')->willReturn(3);

        $orderItemTwo->method('getProductName')->willReturn('PRODUCT_TWO');
        $orderItemTwo->method('getUnitPrice')->willReturn(1000);
        $orderItemTwo->method('getQuantity')->willReturn(3);

        $order->method('getCurrencyCode')->willReturn('EUR');

        $this->orderItemNonNeutralTaxesProvider->method('provide')
            ->willReturnMap([
                [$orderItemOne, [100, 100, 100]],
                [$orderItemTwo, [166, 167, 167]],
            ]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '20.00',
                        'currency_code' => 'EUR',
                    ],
                    'quantity' => 3,
                    'tax' => [
                        'value' => '1.00',
                        'currency_code' => 'EUR',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
                [
                    'name' => 'PRODUCT_TWO',
                    'unit_amount' => [
                        'value' => '10.00',
                        'currency_code' => 'EUR',
                    ],
                    'quantity' => 2,
                    'tax' => [
                        'value' => '1.66',
                        'currency_code' => 'EUR',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
                [
                    'name' => 'PRODUCT_TWO',
                    'unit_amount' => [
                        'value' => '10.00',
                        'currency_code' => 'EUR',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '1.68',
                        'currency_code' => 'EUR',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
            ],
            'total_item_value' => '90.00',
            'total_tax' => '8.00',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function handles_single_cent_remainder_distribution(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('isShippingRequired')->willReturn(true);
        $orderItem->method('getProductName')->willReturn('PRODUCT');
        $order->method('getCurrencyCode')->willReturn('GBP');

        $orderItem->method('getUnitPrice')->willReturn(1000);
        $orderItem->method('getQuantity')->willReturn(2);

        $this->orderItemNonNeutralTaxesProvider->method('provide')->with($orderItem)->willReturn([50, 51]);

        $result = $this->provider->provide($order);

        $expected = [
            'items' => [
                [
                    'name' => 'PRODUCT',
                    'unit_amount' => [
                        'value' => '10.00',
                        'currency_code' => 'GBP',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '0.50',
                        'currency_code' => 'GBP',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
                [
                    'name' => 'PRODUCT',
                    'unit_amount' => [
                        'value' => '10.00',
                        'currency_code' => 'GBP',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '0.51',
                        'currency_code' => 'GBP',
                    ],
                    'category' => 'PHYSICAL_GOODS',
                ],
            ],
            'total_item_value' => '20.00',
            'total_tax' => '1.01',
        ];

        self::assertEquals($expected, $result);
    }

    #[Test]
    public function attaches_the_billing_plan_to_the_item_a_vault_request_recurs_for(): void
    {
        $subscription = $this->orderItemWithId(7, 'SUBSCRIPTION');
        $oneOff = $this->orderItemWithId(8, 'ONE_OFF');
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([$subscription, $oneOff]));
        $order->method('getCurrencyCode')->willReturn('USD');
        $this->orderItemNonNeutralTaxesProvider->method('provide')->willReturn([0]);

        $billingPlan = new PayPalBillingPlan([new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, totalCycles: 0, price: 999)]);
        $result = $this->provider->provide($order, new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID, billingPlans: [7 => $billingPlan]));

        self::assertSame($billingPlan->toArray('USD'), $result['items'][0]['billing_plan']);
        self::assertArrayNotHasKey('billing_plan', $result['items'][1]);
    }

    #[Test]
    public function attaches_the_billing_plan_once_to_an_item_split_for_its_tax(): void
    {
        $subscription = $this->orderItemWithId(7, 'SUBSCRIPTION', 2);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([$subscription]));
        $order->method('getCurrencyCode')->willReturn('USD');
        $this->orderItemNonNeutralTaxesProvider->method('provide')->willReturn([50, 51]);

        $result = $this->provider->provide($order, new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID, billingPlans: [7 => new PayPalBillingPlan([new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, totalCycles: 0, price: 999)])]));

        self::assertCount(2, $result['items']);
        self::assertArrayHasKey('billing_plan', $result['items'][0]);
        self::assertArrayNotHasKey('billing_plan', $result['items'][1]);
    }

    #[Test]
    public function attaches_no_billing_plan_without_a_vault_request(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([$this->orderItemWithId(7, 'SUBSCRIPTION')]));
        $order->method('getCurrencyCode')->willReturn('USD');
        $this->orderItemNonNeutralTaxesProvider->method('provide')->willReturn([0]);

        self::assertArrayNotHasKey('billing_plan', $this->provider->provide($order)['items'][0]);
    }

    private function orderItemWithId(int $id, string $productName, int $quantity = 1): OrderItemInterface&MockObject
    {
        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getId')->willReturn($id);
        $orderItem->method('getProductName')->willReturn($productName);
        $orderItem->method('getUnitPrice')->willReturn(999);
        $orderItem->method('getQuantity')->willReturn($quantity);

        return $orderItem;
    }
}
