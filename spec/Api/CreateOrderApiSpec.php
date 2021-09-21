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

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;

final class CreateOrderApiSpec extends ObjectBehavior
{
    function let(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ): void {
        $this->beConstructedWith($client, $paymentReferenceNumberProvider, $payPalItemDataProvider);
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
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(10000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn(null);
        $order->getItemsTotal()->willReturn(9000);
        $order->getShippingTotal()->willReturn(1000);
        $order->isShippingRequired()->willReturn(true);
        $order->getOrderPromotionTotal()->willReturn(0);

        $payPalItemDataProvider->provide($order)->willReturn([
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '90.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                ],
            ],
            'total_item_value' => '90.00',
            'total_tax' => '0.00',
        ]);

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
                    $data['purchase_units'][0]['amount']['value'] === '100.00' &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['amount']['breakdown']['shipping']['value'] === '10.00' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === '90.00' &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN'
                ;
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
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(10000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn($shippingAddress);
        $order->getItemsTotal()->willReturn(9000);
        $order->getShippingTotal()->willReturn(1000);
        $order->isShippingRequired()->willReturn(true);
        $order->getOrderPromotionTotal()->willReturn(0);

        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');

        $payPalItemDataProvider->provide($order)->willReturn([
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '90.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                ],
            ],
            'total_item_value' => '90.00',
            'total_tax' => '0.00',
        ]);

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
                    $data['purchase_units'][0]['amount']['value'] === '100.00' &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['shipping']['name']['full_name'] === 'Gandalf The Grey' &&
                    $data['purchase_units'][0]['shipping']['address']['address_line_1'] === 'Hobbit St. 123' &&
                    $data['purchase_units'][0]['shipping']['address']['admin_area_2'] === 'Minas Tirith' &&
                    $data['purchase_units'][0]['shipping']['address']['postal_code'] === '000' &&
                    $data['purchase_units'][0]['shipping']['address']['country_code'] === 'US' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === '90.00' &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN'
                ;
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
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(20000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn($shippingAddress);
        $order->getItemsTotal()->willReturn(17000);
        $order->getShippingTotal()->willReturn(3000);
        $order->isShippingRequired()->willReturn(true);
        $order->getOrderPromotionTotal()->willReturn(0);

        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');

        $payPalItemDataProvider->provide($order)->willReturn([
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '90.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                ],
                [
                    'name' => 'PRODUCT_TWO',
                    'unit_amount' => [
                        'value' => '40.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 2,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                ],
            ],
            'total_item_value' => '170.00',
            'total_tax' => '0.00',
        ]);

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
                    $data['purchase_units'][0]['amount']['value'] === '200.00' &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['shipping']['name']['full_name'] === 'Gandalf The Grey' &&
                    $data['purchase_units'][0]['shipping']['address']['address_line_1'] === 'Hobbit St. 123' &&
                    $data['purchase_units'][0]['shipping']['address']['admin_area_2'] === 'Minas Tirith' &&
                    $data['purchase_units'][0]['shipping']['address']['postal_code'] === '000' &&
                    $data['purchase_units'][0]['shipping']['address']['country_code'] === 'US' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === '90.00' &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['name'] === 'PRODUCT_TWO' &&
                    $data['purchase_units'][0]['items'][1]['quantity'] === 2 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['value'] === '40.00' &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['currency_code'] === 'PLN'
                ;
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment, 'REFERENCE_ID')->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }

    function it_creates_pay_pal_order_with_non_neutral_tax_and_changed_quantity(
        PayPalClientInterface $client,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        AddressInterface $shippingAddress,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(13000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn($shippingAddress);
        $order->getItemsTotal()->willReturn(12000);
        $order->getShippingTotal()->willReturn(1000);
        $order->isShippingRequired()->willReturn(true);
        $order->getOrderPromotionTotal()->willReturn(0);

        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');

        $payPalItemDataProvider->provide($order)->willReturn([
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '50.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '10.00',
                        'currency_code' => 'PLN',
                    ],
                ],
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '50.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '10.00',
                        'currency_code' => 'PLN',
                    ],
                ],
            ],
            'total_item_value' => '100.00',
            'total_tax' => '20.00',
        ]);

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
                    $data['purchase_units'][0]['amount']['value'] === '130.00' &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['shipping']['name']['full_name'] === 'Gandalf The Grey' &&
                    $data['purchase_units'][0]['shipping']['address']['address_line_1'] === 'Hobbit St. 123' &&
                    $data['purchase_units'][0]['shipping']['address']['admin_area_2'] === 'Minas Tirith' &&
                    $data['purchase_units'][0]['shipping']['address']['postal_code'] === '000' &&
                    $data['purchase_units'][0]['shipping']['address']['country_code'] === 'US' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === '50.00' &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][0]['tax']['value'] === '10.00' &&
                    $data['purchase_units'][0]['items'][0]['tax']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][1]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['value'] === '50.00' &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['tax']['value'] === '10.00' &&
                    $data['purchase_units'][0]['items'][1]['tax']['currency_code'] === 'PLN'
                ;
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment, 'REFERENCE_ID')->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }

    function it_creates_pay_pal_order_with_more_than_one_product_with_different_tax_rates(
        PayPalClientInterface $client,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        AddressInterface $shippingAddress,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(20400);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn($shippingAddress);
        $order->getItemsTotal()->willReturn(17400);
        $order->getShippingTotal()->willReturn(3000);
        $order->isShippingRequired()->willReturn(true);
        $order->getOrderPromotionTotal()->willReturn(0);

        $shippingAddress->getFullName()->willReturn('Gandalf The Grey');
        $shippingAddress->getStreet()->willReturn('Hobbit St. 123');
        $shippingAddress->getCity()->willReturn('Minas Tirith');
        $shippingAddress->getPostcode()->willReturn('000');
        $shippingAddress->getCountryCode()->willReturn('US');

        $payPalItemDataProvider->provide($order)->willReturn([
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '90.00',
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
                        'value' => '40.00',
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
                        'value' => '40.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '1.00',
                        'currency_code' => 'PLN',
                    ],
                ],
            ],
            'total_item_value' => '170.00',
            'total_tax' => '4.00',
        ]);

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
                    $data['purchase_units'][0]['amount']['value'] === '204.00' &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['shipping']['name']['full_name'] === 'Gandalf The Grey' &&
                    $data['purchase_units'][0]['shipping']['address']['address_line_1'] === 'Hobbit St. 123' &&
                    $data['purchase_units'][0]['shipping']['address']['admin_area_2'] === 'Minas Tirith' &&
                    $data['purchase_units'][0]['shipping']['address']['postal_code'] === '000' &&
                    $data['purchase_units'][0]['shipping']['address']['country_code'] === 'US' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === '90.00' &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][0]['tax']['value'] === '2.00' &&
                    $data['purchase_units'][0]['items'][0]['tax']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['name'] === 'PRODUCT_TWO' &&
                    $data['purchase_units'][0]['items'][1]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['value'] === '40.00' &&
                    $data['purchase_units'][0]['items'][1]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][1]['tax']['value'] === '1.00' &&
                    $data['purchase_units'][0]['items'][1]['tax']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][2]['name'] === 'PRODUCT_TWO' &&
                    $data['purchase_units'][0]['items'][2]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][2]['unit_amount']['value'] === '40.00' &&
                    $data['purchase_units'][0]['items'][2]['unit_amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['items'][2]['tax']['value'] === '1.00' &&
                    $data['purchase_units'][0]['items'][2]['tax']['currency_code'] === 'PLN'
                ;
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment, 'REFERENCE_ID')->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }

    function it_allows_to_create_digital_order(
        PayPalClientInterface $client,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        AddressInterface $shippingAddress,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(20000);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn(null);
        $order->getItemsTotal()->willReturn(20000);
        $order->getShippingTotal()->willReturn(0);
        $order->isShippingRequired()->willReturn(false);
        $order->getOrderPromotionTotal()->willReturn(0);

        $payPalItemDataProvider->provide($order)->willReturn([
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '200.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                ],
            ],
            'total_item_value' => '200.00',
            'total_tax' => '0.00',
        ]);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $gatewayConfig->getConfig()->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id']
        );

        $order->setShippingAddress(Argument::any())->shouldNotBeCalled();
        $order->setBillingAddress(Argument::any())->shouldNotBeCalled();

        $paymentReferenceNumberProvider->provide($payment)->willReturn('REFERENCE-NUMBER');
        $client->post(
            'v2/checkout/orders',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data['intent'] === 'CAPTURE' &&
                    $data['purchase_units'][0]['amount']['value'] === '200.00' &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['application_context']['shipping_preference'] === 'NO_SHIPPING'
                ;
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment, 'REFERENCE_ID')->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }

    function it_creates_pay_pal_order_with_promotion(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        PayPalItemDataProviderInterface $payPalItemDataProvider
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(2999);
        $order->getCurrencyCode()->willReturn('PLN');
        $order->getShippingAddress()->willReturn(null);
        $order->getItemsTotal()->willReturn(2250);
        $order->getShippingTotal()->willReturn(749);
        $order->isShippingRequired()->willReturn(true);
        $order->getOrderPromotionTotal()->willReturn(-250);

        $payPalItemDataProvider->provide($order)->willReturn([
            'items' => [
                [
                    'name' => 'PRODUCT_ONE',
                    'unit_amount' => [
                        'value' => '25.00',
                        'currency_code' => 'PLN',
                    ],
                    'quantity' => 1,
                    'tax' => [
                        'value' => '0.00',
                        'currency_code' => 'PLN',
                    ],
                ],
            ],
            'total_item_value' => '25.00',
            'total_tax' => '0.00',
        ]);

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
                    $data['purchase_units'][0]['amount']['value'] === '29.99' &&
                    $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['amount']['breakdown']['shipping']['value'] === '7.49' &&
                    $data['purchase_units'][0]['amount']['breakdown']['item_total']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['amount']['breakdown']['item_total']['value'] === '25.00' &&
                    $data['purchase_units'][0]['amount']['breakdown']['discount']['currency_code'] === 'PLN' &&
                    $data['purchase_units'][0]['amount']['breakdown']['discount']['value'] === '2.50' &&
                    $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                    $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === '25.00' &&
                    $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN'
                    ;
            })
        )->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->create('TOKEN', $payment, 'REFERENCE_ID')->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }
}
