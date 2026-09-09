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
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Sylius\PayPalPlugin\Service\FakeOrderDetailsApi;

final class ProcessPayPalOrderActionTest extends JsonApiTestCase
{
    private const ITEMS_TOTAL = 40;

    private const STANDARD_SHIPPING_COST = 500;

    private const EXPRESS_SHIPPING_COST = 2000;

    public function test_it_completes_the_order_in_one_call_when_the_buyer_approves_in_the_wallet(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        $this->mockOrderDetailsApi([
            'payer' => [
                'email_address' => 'oliver.queen@star-city.com',
                'name' => ['given_name' => 'Oliver', 'surname' => 'Queen'],
                'phone' => ['phone_number' => ['national_number' => '15551234567']],
                'address' => ['country_code' => 'US'],
            ],
            'purchase_units' => [[
                'amount' => ['value' => '0.60'],
                'shipping' => [
                    'name' => ['full_name' => 'Oliver Queen'],
                    'address' => [
                        'address_line_1' => '1 Star City Plaza',
                        'admin_area_2' => 'Star City',
                        'postal_code' => '10001',
                        'country_code' => 'US',
                    ],
                ],
            ]],
        ]);
        $this->mockSuccessfulPaymentCompleteProcessor();

        $orderId = $order->getId();
        $content = $this->processPayPalOrder('TOKEN');
        $order = $this->refreshOrder($orderId);

        $this->assertSame($orderId, $content['orderID']);
        $this->assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);
        $this->assertSame('completed', $order->getCheckoutState());

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        $this->assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function test_it_creates_a_new_customer_from_the_pay_pal_payer_data_when_the_order_has_none_yet(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart_without_customer.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart_without_customer'];

        $this->mockOrderDetailsApi([
            'payer' => [
                'email_address' => 'new.buyer@example.com',
                'name' => ['given_name' => 'Jane', 'surname' => 'Doe'],
                'phone' => ['phone_number' => ['national_number' => '15559876543']],
                'address' => ['country_code' => 'US'],
            ],
            'purchase_units' => [[
                'amount' => ['value' => '0.60'],
                'shipping' => [
                    'name' => ['full_name' => 'Jane Doe'],
                    'address' => [
                        'address_line_1' => '42 Wallaby Way',
                        'admin_area_2' => 'Sydney',
                        'postal_code' => '20500',
                        'country_code' => 'US',
                    ],
                ],
            ]],
        ]);
        $this->mockSuccessfulPaymentCompleteProcessor();

        $orderId = $order->getId();
        $content = $this->processPayPalOrder('TOKEN_NO_CUSTOMER');
        $order = $this->refreshOrder($orderId);

        $this->assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);

        /** @var CustomerInterface $customer */
        $customer = $order->getCustomer();
        $this->assertNotNull($customer);
        $this->assertSame('new.buyer@example.com', $customer->getEmail());
        $this->assertSame('15559876543', $customer->getPhoneNumber());
    }

    public function test_it_frees_the_order_and_returns_the_buyer_to_checkout_when_the_amount_does_not_match(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        /** @var PaymentInterface $originalPayment */
        $originalPayment = $fixtures['paypal_payment'];
        $originalPaymentId = $originalPayment->getId();

        $this->mockOrderDetailsApi([
            'payer' => [
                'email_address' => 'oliver.queen@star-city.com',
                'name' => ['given_name' => 'Oliver', 'surname' => 'Queen'],
                'address' => ['country_code' => 'US'],
            ],
            'purchase_units' => [[
                'amount' => ['value' => '999.00'],
                'shipping' => [
                    'name' => ['full_name' => 'Oliver Queen'],
                    'address' => [
                        'address_line_1' => '1 Star City Plaza',
                        'admin_area_2' => 'Star City',
                        'postal_code' => '10001',
                        'country_code' => 'US',
                    ],
                ],
            ]],
        ]);

        $orderId = $order->getId();
        $content = $this->processPayPalOrder('TOKEN');
        $order = $this->refreshOrder($orderId);

        $this->assertSame($this->generateUrl('sylius_shop_checkout_complete'), $content['return_url']);
        $this->assertNotSame('completed', $order->getCheckoutState());

        /** @var PaymentInterface|null $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $this->assertNotNull($payment);
        $this->assertNotSame($originalPaymentId, $payment->getId());
    }

    public function test_it_refuses_the_request_when_the_pay_pal_order_id_does_not_match_the_payment(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];

        $orderId = $order->getId();
        $paymentId = $payment->getId();
        $content = $this->processPayPalOrder('TOKEN', 'OTHER_PAYPAL_ORDER_ID');
        $order = $this->refreshOrder($orderId);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $this->assertSame($this->generateUrl('sylius_shop_checkout_complete'), $content['return_url']);
        $this->assertSame('shipping_selected', $order->getCheckoutState());
        $this->assertNull($order->getShippingAddress());

        /** @var PaymentInterface|null $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $this->assertNotNull($payment);
        $this->assertSame($paymentId, $payment->getId());
    }

    public function test_it_refuses_the_request_when_the_payment_carries_no_pay_pal_order_id(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];

        $orderId = $order->getId();
        $paymentId = $payment->getId();
        $this->clearPaymentDetails($paymentId);
        $content = $this->processPayPalOrder('TOKEN');
        $order = $this->refreshOrder($orderId);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $this->assertSame($this->generateUrl('sylius_shop_checkout_complete'), $content['return_url']);
        $this->assertSame('shipping_selected', $order->getCheckoutState());
        $this->assertNull($order->getShippingAddress());

        /** @var PaymentInterface|null $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $this->assertNotNull($payment);
        $this->assertSame($paymentId, $payment->getId());
    }

    public function test_it_returns_the_buyer_to_the_thank_you_page_when_the_order_is_already_completed(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        $content = $this->processPayPalOrder('TOKEN');

        $this->assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);
    }

    public function test_it_applies_the_shipping_method_the_buyer_picked_in_the_wallet(): void
    {
        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/new_cart.yaml',
        ]);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        $this->mockOrderDetailsApi($this->orderDetails(
            shippingOptions: [
                ['id' => 'STANDARD', 'amount' => ['currency_code' => 'USD', 'value' => '5.00'], 'selected' => false],
                ['id' => 'EXPRESS', 'amount' => ['currency_code' => 'USD', 'value' => '20.00'], 'selected' => true],
            ],
            shippingTotal: self::EXPRESS_SHIPPING_COST,
        ));
        $this->mockSuccessfulPaymentCompleteProcessor();

        $orderId = $order->getId();
        $content = $this->processPayPalOrder('TOKEN');
        $order = $this->refreshOrder($orderId);

        $shipment = $order->getShipments()->first();
        $this->assertInstanceOf(ShipmentInterface::class, $shipment);

        $shippingMethod = $shipment->getMethod();
        $this->assertNotNull($shippingMethod);
        $this->assertSame('EXPRESS', $shippingMethod->getCode());
        $this->assertSame(self::EXPRESS_SHIPPING_COST, $order->getShippingTotal());
        $this->assertSame(self::ITEMS_TOTAL, $order->getItemsTotal());
        $this->assertSame(self::ITEMS_TOTAL + self::EXPRESS_SHIPPING_COST, $order->getTotal());
        $this->assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);
    }

    public function test_it_keeps_the_default_shipping_method_when_the_wallet_sends_no_options(): void
    {
        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/new_cart.yaml',
        ]);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        $this->mockOrderDetailsApi($this->orderDetails());
        $this->mockSuccessfulPaymentCompleteProcessor();

        $orderId = $order->getId();
        $this->processPayPalOrder('TOKEN');
        $order = $this->refreshOrder($orderId);

        $shipment = $order->getShipments()->first();
        $this->assertInstanceOf(ShipmentInterface::class, $shipment);

        $shippingMethod = $shipment->getMethod();
        $this->assertNotNull($shippingMethod);
        $this->assertSame('STANDARD', $shippingMethod->getCode());
        $this->assertSame(self::STANDARD_SHIPPING_COST, $order->getShippingTotal());
    }

    public function test_it_stores_the_region_of_the_pay_pal_shipping_address_as_a_province_code(): void
    {
        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/new_cart.yaml',
        ]);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        $this->mockOrderDetailsApi($this->orderDetails(adminArea1: 'TX'));
        $this->mockSuccessfulPaymentCompleteProcessor();

        $orderId = $order->getId();
        $this->processPayPalOrder('TOKEN');
        $order = $this->refreshOrder($orderId);

        $shippingAddress = $order->getShippingAddress();
        $this->assertNotNull($shippingAddress);
        $this->assertSame('US-TX', $shippingAddress->getProvinceCode());
        $this->assertNull($shippingAddress->getProvinceName());
    }

    public function test_it_keeps_an_unknown_region_as_a_province_name(): void
    {
        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/new_cart.yaml',
        ]);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        $this->mockOrderDetailsApi($this->orderDetails(adminArea1: 'Nowhere County'));
        $this->mockSuccessfulPaymentCompleteProcessor();

        $orderId = $order->getId();
        $this->processPayPalOrder('TOKEN');
        $order = $this->refreshOrder($orderId);

        $shippingAddress = $order->getShippingAddress();
        $this->assertNotNull($shippingAddress);
        $this->assertNull($shippingAddress->getProvinceCode());
        $this->assertSame('Nowhere County', $shippingAddress->getProvinceName());
    }

    /**
     * @param array<int, array<string, mixed>> $shippingOptions
     *
     * @return array<string, mixed>
     */
    private function orderDetails(
        array $shippingOptions = [],
        ?string $adminArea1 = null,
        int $shippingTotal = self::STANDARD_SHIPPING_COST,
    ): array {
        $address = [
            'address_line_1' => '1 Star City Plaza',
            'admin_area_2' => 'Star City',
            'postal_code' => '10001',
            'country_code' => 'US',
        ];

        if (null !== $adminArea1) {
            $address['admin_area_1'] = $adminArea1;
        }

        $shipping = ['name' => ['full_name' => 'Oliver Queen'], 'address' => $address];

        if ([] !== $shippingOptions) {
            $shipping['options'] = $shippingOptions;
        }

        return [
            'payer' => [
                'email_address' => 'oliver.queen@star-city.com',
                'name' => ['given_name' => 'Oliver', 'surname' => 'Queen'],
                'phone' => ['phone_number' => ['national_number' => '15551234567']],
                'address' => ['country_code' => 'US'],
            ],
            'purchase_units' => [[
                'amount' => ['value' => number_format((self::ITEMS_TOTAL + $shippingTotal) / 100, 2, '.', '')],
                'shipping' => $shipping,
            ]],
        ];
    }

    public function test_it_returns_not_found_for_a_foreign_or_unknown_token(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);

        $this->processPayPalOrder('FOREIGN_TOKEN');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /** @return array<string, mixed> */
    private function processPayPalOrder(string $tokenValue, string $payPalOrderId = 'PAYPAL_ORDER_ID'): array
    {
        $this->client->request(
            'POST',
            '/en_US/process-pay-pal-order/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['payPalOrderId' => $payPalOrderId, 'tokenValue' => $tokenValue]),
        );

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function clearPaymentDetails(int $paymentId): void
    {
        $manager = self::getContainer()->get('sylius.manager.payment');
        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.repository.payment')->find($paymentId);

        $payment->setDetails([]);
        $manager->flush();
    }

    private function refreshOrder(int $orderId): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        return $order;
    }

    /** @param array<string, mixed> $orderDetails */
    private function mockOrderDetailsApi(array $orderDetails): void
    {
        self::getContainer()->set('sylius_paypal.api.order_details', new FakeOrderDetailsApi($orderDetails));
    }

    private function mockSuccessfulPaymentCompleteProcessor(): void
    {
        self::getContainer()->set('sylius_paypal.processor.payment_complete', new class() implements PaymentCompleteProcessorInterface {
            public function completePayment(PaymentInterface $payment): void
            {
                $payment->setDetails(['status' => StatusAction::STATUS_COMPLETED]);
            }
        });
    }

    private function generateUrl(string $route): string
    {
        /** @var UrlGeneratorInterface $router */
        $router = self::getContainer()->get('router');

        return $router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
