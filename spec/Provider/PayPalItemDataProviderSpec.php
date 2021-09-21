<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\PayPalPlugin\Provider\OrderItemNonNeutralTaxesProviderInterface;

final class PayPalItemDataProviderSpec extends ObjectBehavior
{
    function let(OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider): void
    {
        $this->beConstructedWith($orderItemNonNeutralTaxesProvider);
    }

    function it_returns_array_of_items_with_tax(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');
        $order->getCurrencyCode()->willReturn('PLN');

        $orderItem->getUnitPrice()->willReturn(2000);
        $orderItem->getQuantity()->willReturn(1);

        $orderItemNonNeutralTaxesProvider->provide($orderItem)->willReturn([200]);

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
            ]
        );
    }

    function it_returns_array_of_items_with_different_quantities_with_tax(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');
        $order->getCurrencyCode()->willReturn('PLN');

        $orderItem->getUnitPrice()->willReturn(2000);
        $orderItem->getQuantity()->willReturn(3);

        $orderItemNonNeutralTaxesProvider->provide($orderItem)->willReturn([200, 200, 200]);

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

    function it_returns_array_of_items_with_different_quantities_without_tax(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');
        $order->getCurrencyCode()->willReturn('PLN');

        $orderItem->getUnitPrice()->willReturn(2000);
        $orderItem->getQuantity()->willReturn(3);

        $orderItemNonNeutralTaxesProvider->provide($orderItem)->willReturn([0]);

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
        OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider
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

        $orderItemNonNeutralTaxesProvider->provide($orderItemOne)->willReturn([0]);
        $orderItemNonNeutralTaxesProvider->provide($orderItemTwo)->willReturn([0]);

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

    function it_returns_array_of_different_items_with_different_quantities_with_tax(
        OrderInterface $order,
        OrderItemInterface $orderItemOne,
        OrderItemInterface $orderItemTwo,
        OrderItemNonNeutralTaxesProviderInterface $orderItemNonNeutralTaxesProvider
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItemOne->getWrappedObject(), $orderItemTwo->getWrappedObject()]));
        $orderItemOne->getProductName()->willReturn('PRODUCT_ONE');
        $orderItemOne->getUnitPrice()->willReturn(2000);
        $orderItemOne->getQuantity()->willReturn(3);

        $orderItemTwo->getProductName()->willReturn('PRODUCT_TWO');
        $orderItemTwo->getUnitPrice()->willReturn(1000);
        $orderItemTwo->getQuantity()->willReturn(2);

        $order->getCurrencyCode()->willReturn('PLN');

        $orderItemNonNeutralTaxesProvider->provide($orderItemOne)->willReturn([100, 100, 100]);
        $orderItemNonNeutralTaxesProvider->provide($orderItemTwo)->willReturn([200, 100]);

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
                            'value' => '10.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '2.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                    [
                        'name' => 'PRODUCT_TWO',
                        'unit_amount' => [
                            'value' => '10.00',
                            'currency_code' => 'PLN',
                        ],
                        'quantity' => 1,
                        'tax' => [
                            'value' => '1.00',
                            'currency_code' => 'PLN',
                        ],
                    ],
                ],
                'total_item_value' => '80.00',
                'total_tax' => '6.00',
            ],
        );
    }
}
