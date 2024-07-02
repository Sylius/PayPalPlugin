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

namespace spec\Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\PayPalPlugin\Provider\OrderItemTaxesProviderInterface;

final class PayPalItemDataProviderSpec extends ObjectBehavior
{
    function let(OrderItemTaxesProviderInterface $orderItemTaxesProvider): void
    {
        $this->beConstructedWith($orderItemTaxesProvider);
    }

    function it_returns_array_of_items_with_non_neutral_tax(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        OrderItemTaxesProviderInterface $orderItemTaxesProvider,
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');
        $order->getCurrencyCode()->willReturn('PLN');

        $orderItem->getUnitPrice()->willReturn(2000);
        $orderItem->getQuantity()->willReturn(1);

        $orderItemTaxesProvider->provide($orderItem)->willReturn([
            'total' => 200,
            'itemTaxes' => [1 => [0 => 200, 1 => 0]],
        ]);

        $this->provide($order)->shouldReturn(
            [
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
                    ],
                ],
                'total_item_value' => '20.00',
                'total_tax' => '2.00',
            ],
        );
    }

    function it_returns_array_of_items_with_neutral_tax(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        OrderItemTaxesProviderInterface $orderItemTaxesProvider,
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');
        $order->getCurrencyCode()->willReturn('PLN');

        $orderItem->getUnitPrice()->willReturn(2000);
        $orderItem->getQuantity()->willReturn(1);

        $orderItemTaxesProvider->provide($orderItem)->willReturn([
            'total' => 200,
            'itemTaxes' => [1 => [0 => 0, 1 => 200]],
        ]);

        $this->provide($order)->shouldReturn(
            [
                'items' => [
                    [
                        'name' => 'PRODUCT_ONE',
                        'unit_amount' => [
                            'value' => '18.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '2.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                ],
                'total_item_value' => '18.00',
                'total_tax' => '2.00',
            ],
        );
    }

    function it_returns_array_of_items_with_different_quantities_with_non_neutral_tax(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        OrderItemTaxesProviderInterface $orderItemTaxesProvider,
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');
        $order->getCurrencyCode()->willReturn('PLN');

        $orderItem->getUnitPrice()->willReturn(2000);
        $orderItem->getQuantity()->willReturn(3);

        $orderItemTaxesProvider->provide($orderItem)->willReturn([
            'total' => 600,
            'itemTaxes' => [
                1 => [0 => 200, 1 => 0],
                2 => [0 => 200, 1 => 0],
                3 => [0 => 200, 1 => 0],
            ],
        ]);

        $this->provide($order)->shouldReturn(
            [
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
                    ],
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
                    ],
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
                    ],
                ],
                'total_item_value' => '60.00',
                'total_tax' => '6.00',
            ],
        );
    }

    function it_returns_array_of_items_with_different_quantities_with_neutral_tax(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        OrderItemTaxesProviderInterface $orderItemTaxesProvider,
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');
        $order->getCurrencyCode()->willReturn('PLN');

        $orderItem->getUnitPrice()->willReturn(2000);
        $orderItem->getQuantity()->willReturn(3);

        $orderItemTaxesProvider->provide($orderItem)->willReturn([
            'total' => 600,
            'itemTaxes' => [
                1 => [0 => 0, 1 => 200],
                2 => [0 => 0, 1 => 200],
                3 => [0 => 0, 1 => 200],
            ],
        ]);

        $this->provide($order)->shouldReturn(
            [
                'items' => [
                    [
                        'name' => 'PRODUCT_ONE',
                        'unit_amount' => [
                            'value' => '18.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '2.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                    [
                        'name' => 'PRODUCT_ONE',
                        'unit_amount' => [
                            'value' => '18.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '2.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                    [
                        'name' => 'PRODUCT_ONE',
                        'unit_amount' => [
                            'value' => '18.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '2.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                ],
                'total_item_value' => '54.00',
                'total_tax' => '6.00',
            ],
        );
    }

    function it_returns_array_of_items_with_different_quantities_without_tax(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        OrderItemTaxesProviderInterface $orderItemTaxesProvider,
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');
        $order->getCurrencyCode()->willReturn('PLN');

        $orderItem->getUnitPrice()->willReturn(2000);
        $orderItem->getQuantity()->willReturn(3);

        $orderItemTaxesProvider->provide($orderItem)->willReturn(['total' => 0]);

        $this->provide($order)->shouldReturn(
            [
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
                    ],
                ],
                'total_item_value' => '60.00',
                'total_tax' => '0.00',
            ],
        );
    }

    function it_returns_array_of_different_items_with_different_quantities_without_tax(
        OrderInterface $order,
        OrderItemInterface $orderItemOne,
        OrderItemInterface $orderItemTwo,
        OrderItemTaxesProviderInterface $orderItemTaxesProvider,
    ): void {
        $order->getItems()
            ->willReturn(new ArrayCollection([$orderItemOne->getWrappedObject(), $orderItemTwo->getWrappedObject()]));
        $orderItemOne->getProductName()->willReturn('PRODUCT_ONE');
        $orderItemOne->getUnitPrice()->willReturn(2000);
        $orderItemOne->getQuantity()->willReturn(3);

        $orderItemTwo->getProductName()->willReturn('PRODUCT_TWO');
        $orderItemTwo->getUnitPrice()->willReturn(1000);
        $orderItemTwo->getQuantity()->willReturn(2);

        $order->getCurrencyCode()->willReturn('PLN');

        $orderItemTaxesProvider->provide($orderItemOne)->willReturn(['total' => 0]);
        $orderItemTaxesProvider->provide($orderItemTwo)->willReturn(['total' => 0]);

        $this->provide($order)->shouldReturn(
            [
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
                    ],
                ],
                'total_item_value' => '80.00',
                'total_tax' => '0.00',
            ],
        );
    }

    function it_returns_array_of_different_items_with_different_quantities_with_different_taxes(
        OrderInterface $order,
        OrderItemInterface $orderItemOne,
        OrderItemInterface $orderItemTwo,
        OrderItemTaxesProviderInterface $orderItemTaxesProvider,
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItemOne->getWrappedObject(), $orderItemTwo->getWrappedObject()]));
        $orderItemOne->getProductName()->willReturn('PRODUCT_ONE');
        $orderItemOne->getUnitPrice()->willReturn(2000);
        $orderItemOne->getQuantity()->willReturn(3);

        $orderItemTwo->getProductName()->willReturn('PRODUCT_TWO');
        $orderItemTwo->getUnitPrice()->willReturn(1000);
        $orderItemTwo->getQuantity()->willReturn(2);

        $order->getCurrencyCode()->willReturn('PLN');

        $orderItemTaxesProvider->provide($orderItemOne)->willReturn([
            'total' => 300,
            'itemTaxes' => [
                1 => [0 => 100, 1 => 0],
                2 => [0 => 100, 1 => 0],
                3 => [0 => 100, 1 => 0],
            ],
        ]);
        $orderItemTaxesProvider->provide($orderItemTwo)->willReturn([
            'total' => 200,
            'itemTaxes' => [
                1 => [0 => 0, 1 => 100],
                2 => [0 => 0, 1 => 100],
            ],
        ]);

        $this->provide($order)->shouldReturn(
            [
                'items' => [
                    [
                        'name' => 'PRODUCT_ONE',
                        'unit_amount' => [
                            'value' => '20.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '1.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                    [
                        'name' => 'PRODUCT_ONE',
                        'unit_amount' => [
                            'value' => '20.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '1.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                    [
                        'name' => 'PRODUCT_ONE',
                        'unit_amount' => [
                            'value' => '20.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '1.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                    [
                        'name' => 'PRODUCT_TWO',
                        'unit_amount' => [
                            'value' => '9.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '1.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                    [
                        'name' => 'PRODUCT_TWO',
                        'unit_amount' => [
                            'value' => '9.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '1.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                ],
                'total_item_value' => '78.00',
                'total_tax' => '5.00',
            ],
        );
    }
}
