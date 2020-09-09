<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Api;

use Doctrine\Common\Collections\Collection;
use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\OrderItemNonNeutralTaxProviderInterface;

final class CreateOrderApiSpec extends ObjectBehavior
{
    function let(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        OrderItemNonNeutralTaxProviderInterface $orderItemNonNeutralTaxProvider
    ): void {
        $this->beConstructedWith($client, $paymentReferenceNumberProvider, $orderItemNonNeutralTaxProvider);
    }

    function it_implements_create_order_api_interface(): void
    {
        $this->shouldImplement(CreateOrderApiInterface::class);
    }

    function it_creates_pay_pal_order_based_on_given_payment(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        OrderItemInterface $orderItem,
        Collection $orderItemCollection,
        OrderItemNonNeutralTaxProviderInterface $orderItemNonNeutralTaxProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(10000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn(null);
        $order->getItems()->willReturn($orderItemCollection);
        $order->getItemsTotal()->willReturn(9000);
        $order->getShippingTotal()->willReturn(1000);

        $orderItemNonNeutralTaxProvider->provide($orderItem)->willReturn([0]);
        $orderItemCollection->toArray()->willReturn([$orderItem]);
        $orderItem->getQuantity()->willReturn(1);
        $orderItem->getUnitPrice()->willReturn(9000);
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $paymentReferenceNumberProvider->provide($payment)->willReturn('REFERENCE-NUMBER');

        $gatewayConfig->getConfig()->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id']
        );

        $client->post(
            'v2/checkout/orders',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data['intent'] === 'CAPTURE' &&
                    $data['purchase_units'][0]['invoice_number'] === 'REFERENCE-NUMBER' &&
                    $data['purchase_units'][0]['amount']['value'] === 100 &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['amount']['breakdown']['shipping']['value'] === 10 &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === 90 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN';
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment, 'REFERENCE_ID')->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }

    function it_creates_pay_pal_order_with_shipping_address_based_on_given_payment(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        AddressInterface $shippingAddress,
        OrderItemInterface $orderItem,
        Collection $orderItemCollection,
        OrderItemNonNeutralTaxProviderInterface $orderItemNonNeutralTaxProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(10000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn($shippingAddress);
        $order->getItems()->willReturn($orderItemCollection);
        $order->getItemsTotal()->willReturn(9000);
        $order->getShippingTotal()->willReturn(1000);

        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');

        $orderItemNonNeutralTaxProvider->provide($orderItem)->willReturn([0]);

        $orderItemCollection->toArray()->willReturn([$orderItem]);
        $orderItem->getQuantity()->willReturn(1);
        $orderItem->getUnitPrice()->willReturn(9000);
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $paymentReferenceNumberProvider->provide($payment)->willReturn('REFERENCE-NUMBER');

        $gatewayConfig->getConfig()->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id']
        );

        $client->post(
            'v2/checkout/orders',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data['intent'] === 'CAPTURE' &&
                    $data['purchase_units'][0]['invoice_number'] === 'REFERENCE-NUMBER' &&
                    $data['purchase_units'][0]['amount']['value'] === 100 &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['shipping']['name']['full_name'] === 'Gandalf The Grey' &&
                    $data['purchase_units'][0]['shipping']['address']['address_line_1'] === 'Hobbit St. 123' &&
                    $data['purchase_units'][0]['shipping']['address']['admin_area_2'] === 'Minas Tirith' &&
                    $data['purchase_units'][0]['shipping']['address']['postal_code'] === '000' &&
                    $data['purchase_units'][0]['shipping']['address']['country_code'] === 'US' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === 90 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN';
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment, 'REFERENCE_ID')->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }

    function it_creates_pay_pal_order_with_more_than_one_product(
        PayPalClientInterface $client,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        AddressInterface $shippingAddress,
        OrderItemInterface $orderItemOne,
        OrderItemInterface $orderItemTwo,
        Collection $orderItemCollection,
        OrderItemNonNeutralTaxProviderInterface $orderItemNonNeutralTaxProvider,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(20000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn($shippingAddress);
        $order->getItems()->willReturn($orderItemCollection);
        $order->getItemsTotal()->willReturn(17000);
        $order->getShippingTotal()->willReturn(3000);

        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');

        $orderItemCollection->toArray()->willReturn([$orderItemOne, $orderItemTwo]);
        $orderItemOne->getQuantity()->willReturn(1);
        $orderItemOne->getUnitPrice()->willReturn(9000);
        $orderItemOne->getProductName()->willReturn('PRODUCT_ONE');

        $orderItemTwo->getQuantity()->willReturn(2);
        $orderItemTwo->getUnitPrice()->willReturn(4000);
        $orderItemTwo->getProductName()->willReturn('PRODUCT_TWO');

        $orderItemNonNeutralTaxProvider->provide($orderItemOne)->willReturn([0]);
        $orderItemNonNeutralTaxProvider->provide($orderItemTwo)->willReturn([0]);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $gatewayConfig->getConfig()->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id']
        );

        $paymentReferenceNumberProvider->provide($payment)->willReturn('REFERENCE-NUMBER');

        $client->post(
            'v2/checkout/orders',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data['intent'] === 'CAPTURE' &&
                    $data['purchase_units'][0]['amount']['value'] === 200 &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['shipping']['name']['full_name'] === 'Gandalf The Grey' &&
                    $data['purchase_units'][0]['shipping']['address']['address_line_1'] === 'Hobbit St. 123' &&
                    $data['purchase_units'][0]['shipping']['address']['admin_area_2'] === 'Minas Tirith' &&
                    $data['purchase_units'][0]['shipping']['address']['postal_code'] === '000' &&
                    $data['purchase_units'][0]['shipping']['address']['country_code'] === 'US' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === 90 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['name'] === 'PRODUCT_TWO' &&
                    $data['purchase_units'][0]['items'][1]['quantity'] === 2 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['value'] === 40 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['currency_code'] === 'PLN';
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment)->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }

    function it_creates_pay_pal_order_with_non_neutral_tax_and_changed_quantity(
        PayPalClientInterface $client,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        AddressInterface $shippingAddress,
        OrderItemInterface $orderItem,
        Collection $orderItemCollection,
        OrderItemNonNeutralTaxProviderInterface $orderItemNonNeutralTaxProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(13000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn($shippingAddress);
        $order->getItems()->willReturn($orderItemCollection);
        $order->getItemsTotal()->willReturn(12000);
        $order->getShippingTotal()->willReturn(1000);

        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');

        $orderItemNonNeutralTaxProvider->provide($orderItem)->willReturn([100, 100]);

        $orderItemCollection->toArray()->willReturn([$orderItem]);
        $orderItem->getQuantity()->willReturn(2);
        $orderItem->getUnitPrice()->willReturn(5000);
        $orderItem->getProductName()->willReturn('PRODUCT_ONE');

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $gatewayConfig->getConfig()->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id']
        );

        $client->post(
            'v2/checkout/orders',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data['intent'] === 'CAPTURE' &&
                    $data['purchase_units'][0]['amount']['value'] === 130 &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['shipping']['name']['full_name'] === 'Gandalf The Grey' &&
                    $data['purchase_units'][0]['shipping']['address']['address_line_1'] === 'Hobbit St. 123' &&
                    $data['purchase_units'][0]['shipping']['address']['admin_area_2'] === 'Minas Tirith' &&
                    $data['purchase_units'][0]['shipping']['address']['postal_code'] === '000' &&
                    $data['purchase_units'][0]['shipping']['address']['country_code'] === 'US' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === 50 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][0]['tax']['value'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['tax']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][1]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['value'] === 50 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['tax']['value'] === 1 &&
                    $data['purchase_units'][0]['items'][1]['tax']['currency_code'] === 'PLN';
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment)->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }

    function it_creates_pay_pal_order_with_more_than_one_product_with_different_tax_rates(
        PayPalClientInterface $client,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        AddressInterface $shippingAddress,
        OrderItemInterface $orderItemOne,
        OrderItemInterface $orderItemTwo,
        Collection $orderItemCollection,
        OrderItemNonNeutralTaxProviderInterface $orderItemNonNeutralTaxProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(20300);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn($shippingAddress);
        $order->getItems()->willReturn($orderItemCollection);
        $order->getItemsTotal()->willReturn(17300);
        $order->getShippingTotal()->willReturn(3000);

        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');
        $orderItemCollection->toArray()->willReturn([$orderItemOne, $orderItemTwo]);
        $orderItemOne->getQuantity()->willReturn(1);
        $orderItemOne->getUnitPrice()->willReturn(9000);
        $orderItemOne->getProductName()->willReturn('PRODUCT_ONE');

        $orderItemTwo->getQuantity()->willReturn(2);
        $orderItemTwo->getUnitPrice()->willReturn(4000);
        $orderItemTwo->getProductName()->willReturn('PRODUCT_TWO');

        $orderItemNonNeutralTaxProvider->provide($orderItemOne)->willReturn([200]);
        $orderItemNonNeutralTaxProvider->provide($orderItemTwo)->willReturn([100, 100]);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $gatewayConfig->getConfig()->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id']
        );

        $client->post(
            'v2/checkout/orders',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data['intent'] === 'CAPTURE' &&
                    $data['purchase_units'][0]['amount']['value'] === 203 &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['shipping']['name']['full_name'] === 'Gandalf The Grey' &&
                    $data['purchase_units'][0]['shipping']['address']['address_line_1'] === 'Hobbit St. 123' &&
                    $data['purchase_units'][0]['shipping']['address']['admin_area_2'] === 'Minas Tirith' &&
                    $data['purchase_units'][0]['shipping']['address']['postal_code'] === '000' &&
                    $data['purchase_units'][0]['shipping']['address']['country_code'] === 'US' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === 90 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][0]['tax']['value'] === 2 &&
                    $data['purchase_units'][0]['items'][0]['tax']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['name'] === 'PRODUCT_TWO' &&
                    $data['purchase_units'][0]['items'][1]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['value'] === 40 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['tax']['value'] === 1 &&
                    $data['purchase_units'][0]['items'][1]['tax']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][2]['name'] === 'PRODUCT_TWO' &&
                    $data['purchase_units'][0]['items'][2]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][2]['unit_amount']['value'] === 40 &&
                    $data['purchase_units'][0]['items'][2]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][2]['tax']['value'] === 1 &&
                    $data['purchase_units'][0]['items'][2]['tax']['currency_code'] === 'PLN'
                ;
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment, 'REFERENCE_ID')->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }
}
