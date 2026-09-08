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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApi;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateOrderApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentReferenceNumberProviderInterface&MockObject $paymentReferenceNumberProvider;

    private PayPalItemDataProviderInterface&MockObject $payPalItemDataProvider;

    private UrlGeneratorInterface&MockObject $router;

    private string $shopScheme = 'https';

    private CreateOrderApi $createOrderApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentReferenceNumberProvider = $this->createMock(PaymentReferenceNumberProviderInterface::class);
        $this->payPalItemDataProvider = $this->createMock(PayPalItemDataProviderInterface::class);
        $this->router = $this->createMock(UrlGeneratorInterface::class);

        $this->router->method('generate')->willReturnCallback($this->routeUrl(...));

        $this->createOrderApi = new CreateOrderApi(
            $this->client,
            $this->paymentReferenceNumberProvider,
            $this->payPalItemDataProvider,
            $this->router,
        );
    }

    #[Test]
    public function it_implements_create_order_api_interface(): void
    {
        self::assertInstanceOf(CreateOrderApiInterface::class, $this->createOrderApi);
    }

    #[Test]
    public function it_creates_paypal_order_based_on_given_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAmount')->willReturn(10000);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getItemsTotal')->willReturn(9000);
        $order->method('getShippingTotal')->willReturn(1000);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT)
            ->willReturn(0);

        $this->payPalItemDataProvider
            ->method('provide')
            ->with($order)
            ->willReturn([
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

        $payment->method('getMethod')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->paymentReferenceNumberProvider
            ->method('provide')
            ->with($payment)
            ->willReturn('REFERENCE-NUMBER');

        $gatewayConfig->method('getConfig')->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id'],
        );

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/checkout/orders',
                'TOKEN',
                $this->callback(function (array $data): bool {
                    return
                        $data['intent'] === 'CAPTURE' &&
                        $data['purchase_units'][0]['invoice_id'] === 'REFERENCE-NUMBER' &&
                        $data['purchase_units'][0]['amount']['value'] === '100.00' &&
                        $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                        $data['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code'] === 'PLN' &&
                        $data['purchase_units'][0]['amount']['breakdown']['shipping']['value'] === '10.00' &&
                        $data['purchase_units'][0]['items'][0]['name'] === 'PRODUCT_ONE' &&
                        $data['purchase_units'][0]['items'][0]['quantity'] === 1 &&
                        $data['purchase_units'][0]['items'][0]['unit_amount']['value'] === '90.00' &&
                        $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] === 'PLN'
                    ;
                }),
            )
            ->willReturn(['status' => 'CREATED', 'id' => 123]);

        $result = $this->createOrderApi->create('TOKEN', $payment, 'REFERENCE_ID');

        self::assertEquals(['status' => 'CREATED', 'id' => 123], $result);
    }

    #[Test]
    public function it_creates_paypal_order_with_shipping_address_based_on_given_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAmount')->willReturn(10000);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getItemsTotal')->willReturn(9000);
        $order->method('getShippingTotal')->willReturn(1000);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT)
            ->willReturn(0);

        $shippingAddress->method('getFullName')->willReturn('Gandalf The Grey');
        $shippingAddress->method('getStreet')->willReturn('Hobbit St. 123');
        $shippingAddress->method('getCity')->willReturn('Minas Tirith');
        $shippingAddress->method('getPostcode')->willReturn('000');
        $shippingAddress->method('getCountryCode')->willReturn('US');

        $this->payPalItemDataProvider
            ->method('provide')
            ->with($order)
            ->willReturn([
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

        $payment->method('getMethod')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->paymentReferenceNumberProvider
            ->method('provide')
            ->with($payment)
            ->willReturn('REFERENCE-NUMBER');

        $gatewayConfig->method('getConfig')->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id'],
        );

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/checkout/orders',
                'TOKEN',
                $this->callback(function (array $data): bool {
                    return
                        $data['intent'] === 'CAPTURE' &&
                        $data['purchase_units'][0]['invoice_id'] === 'REFERENCE-NUMBER' &&
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
                }),
            )
            ->willReturn(['status' => 'CREATED', 'id' => 123]);

        $result = $this->createOrderApi->create('TOKEN', $payment, 'REFERENCE_ID');

        self::assertEquals(['status' => 'CREATED', 'id' => 123], $result);
    }

    #[Test]
    public function it_allows_to_create_digital_order(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAmount')->willReturn(20000);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getItemsTotal')->willReturn(20000);
        $order->method('getShippingTotal')->willReturn(0);
        $order->method('isShippingRequired')->willReturn(false);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT)
            ->willReturn(0);

        $this->payPalItemDataProvider
            ->method('provide')
            ->with($order)
            ->willReturn([
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

        $payment->method('getMethod')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $gatewayConfig->method('getConfig')->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id'],
        );

        $this->paymentReferenceNumberProvider
            ->method('provide')
            ->with($payment)
            ->willReturn('REFERENCE-NUMBER');

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/checkout/orders',
                'TOKEN',
                $this->callback(function (array $data): bool {
                    return
                        $data['intent'] === 'CAPTURE' &&
                        $data['purchase_units'][0]['amount']['value'] === '200.00' &&
                        $data['purchase_units'][0]['amount']['currency_code'] === 'PLN' &&
                        $data['application_context']['shipping_preference'] === 'NO_SHIPPING'
                    ;
                }),
            )
            ->willReturn(['status' => 'CREATED', 'id' => 123]);

        $result = $this->createOrderApi->create('TOKEN', $payment, 'REFERENCE_ID');

        self::assertEquals(['status' => 'CREATED', 'id' => 123], $result);
    }

    #[Test]
    public function it_sends_the_same_return_and_cancel_url_on_orders_addressed_in_the_wallet(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAmount')->willReturn(10000);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getShippingTotal')->willReturn(1000);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')->willReturn(0);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getConfig')->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id'],
        );

        $this->payPalItemDataProvider->method('provide')->willReturn([
            'items' => [],
            'total_item_value' => '90.00',
            'total_tax' => '0.00',
        ]);
        $this->paymentReferenceNumberProvider->method('provide')->willReturn('REFERENCE-NUMBER');

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/checkout/orders',
                'TOKEN',
                $this->callback(function (array $data): bool {
                    return $data['payment_source']['paypal']['experience_context'] === [
                        'shipping_preference' => 'GET_FROM_FILE',
                        'user_action' => 'PAY_NOW',
                        'return_url' => 'https://shop.example.com/checkout/complete',
                        'cancel_url' => 'https://shop.example.com/checkout/complete',
                        'order_update_callback_config' => [
                            'callback_events' => ['SHIPPING_ADDRESS'],
                            'callback_url' => 'https://shop.example.com/pay-pal-order-shipping-callback',
                        ],
                    ];
                }),
            )
            ->willReturn(['status' => 'PAYER_ACTION_REQUIRED', 'id' => 123]);

        $this->createOrderApi->create('TOKEN', $payment, 'REFERENCE_ID');
    }

    #[Test]
    public function it_does_not_select_a_payment_source_on_orders_that_already_carry_an_address(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAmount')->willReturn(10000);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getShippingTotal')->willReturn(1000);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')->willReturn(0);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getConfig')->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id'],
        );

        $this->payPalItemDataProvider->method('provide')->willReturn([
            'items' => [],
            'total_item_value' => '90.00',
            'total_tax' => '0.00',
        ]);
        $this->paymentReferenceNumberProvider->method('provide')->willReturn('REFERENCE-NUMBER');

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/checkout/orders',
                'TOKEN',
                $this->callback(fn (array $data): bool => !array_key_exists('payment_source', $data)),
            )
            ->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->createOrderApi->create('TOKEN', $payment, 'REFERENCE_ID');
    }

    #[Test]
    public function it_does_not_declare_a_shipping_callback_paypal_could_not_reach(): void
    {
        $this->shopScheme = 'http';

        $payment = $this->walletAddressedPayment();

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/checkout/orders',
                'TOKEN',
                $this->callback(function (array $data): bool {
                    $experienceContext = $data['payment_source']['paypal']['experience_context'];

                    // return_url stays: it is a browser redirect the buyer can follow on a local shop,
                    // unlike the callback PayPal has to reach from its own network.
                    return
                        !array_key_exists('order_update_callback_config', $experienceContext) &&
                        $experienceContext['return_url'] === 'http://shop.example.com/checkout/complete'
                    ;
                }),
            )
            ->willReturn(['status' => 'PAYER_ACTION_REQUIRED', 'id' => 123]);

        $this->createOrderApi->create('TOKEN', $payment, 'REFERENCE_ID');
    }

    private function walletAddressedPayment(): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAmount')->willReturn(10000);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getShippingTotal')->willReturn(1000);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')->willReturn(0);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getConfig')->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id'],
        );

        $this->payPalItemDataProvider->method('provide')->willReturn([
            'items' => [],
            'total_item_value' => '90.00',
            'total_tax' => '0.00',
        ]);
        $this->paymentReferenceNumberProvider->method('provide')->willReturn('REFERENCE-NUMBER');

        return $payment;
    }

    private function routeUrl(string $route): string
    {
        $path = match ($route) {
            'sylius_shop_checkout_complete' => '/checkout/complete',
            'sylius_paypal_shop_order_shipping_callback' => '/pay-pal-order-shipping-callback',
        };

        return $this->shopScheme . '://shop.example.com' . $path;
    }
}
