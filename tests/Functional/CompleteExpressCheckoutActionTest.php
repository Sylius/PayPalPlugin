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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Storage\CartStorageInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompleteExpressCheckoutActionTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    private const MUG_PRICE = 20;

    private const STANDARD_SHIPPING_COST = 500;

    private const EXPRESS_SHIPPING_COST = 2000;

    public function test_it_places_the_order_and_captures_it_through_a_payment_request(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order));
        $this->mockCapture($this->total($order));

        $orderId = $order->getId();
        $content = $this->completeExpressCheckout($orderId);
        $order = $this->refreshOrder($orderId);

        $paymentRequest = $this->capturePaymentRequest($order);
        self::assertSame($this->payUrl($paymentRequest), $content['return_url']);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame('completed', $order->getCheckoutState());
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $order->getLastPayment()?->getState());
        self::assertSame($orderId, $this->client->getRequest()->getSession()->get('sylius_order_id'));
    }

    public function test_it_places_the_order_and_authorizes_it_through_a_payment_request_when_the_payment_method_authorizes(): void
    {
        $order = $this->cart();
        $this->authorizeWith($order);

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order));
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockAuthorize();
        $this->payPalApi()->mockOrderDetailsWithAuthorization();

        $orderId = $order->getId();
        $content = $this->completeExpressCheckout($orderId);
        $order = $this->refreshOrder($orderId);

        $paymentRequests = $this->paymentRequests();
        self::assertCount(1, $paymentRequests);
        self::assertSame(PaymentRequestInterface::ACTION_AUTHORIZE, $paymentRequests[0]->getAction());
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequests[0]->getState());
        self::assertSame($this->payUrl($paymentRequests[0]), $content['return_url']);
        self::assertSame('completed', $order->getCheckoutState());
        self::assertSame(OrderPaymentStates::STATE_AUTHORIZED, $order->getPaymentState());
        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $order->getLastPayment()?->getState());
        self::assertSame('AUTHORIZATION_ID', $order->getLastPayment()?->getDetails()['authorization_id'] ?? null);
    }

    public function test_it_sends_the_buyer_to_the_payment_request_when_paypal_declines_the_capture(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order));
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockCaptureOfDeclinedInstrument();

        $orderId = $order->getId();
        $content = $this->completeExpressCheckout($orderId);
        $order = $this->refreshOrder($orderId);

        $paymentRequest = $this->capturePaymentRequest($order);
        self::assertSame($this->payUrl($paymentRequest), $content['return_url']);
        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertSame('completed', $order->getCheckoutState());
        self::assertSame(PaymentInterface::STATE_NEW, $order->getLastPayment()?->getState());
    }

    public function test_it_creates_a_customer_from_the_payer_when_the_order_has_none_yet(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/new_cart_without_customer.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart_without_customer'];
        $this->seedCurrentCart($order);

        $orderDetails = $this->orderDetails($order);
        $orderDetails['payer']['email_address'] = 'new.buyer@example.com';
        $orderDetails['payer']['name'] = ['given_name' => 'Jane', 'surname' => 'Doe'];
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $orderDetails);
        $this->mockCapture($this->total($order));

        $orderId = $order->getId();
        $this->completeExpressCheckout($orderId);

        /** @var CustomerInterface $customer */
        $customer = $this->refreshOrder($orderId)->getCustomer();
        self::assertSame('new.buyer@example.com', $customer->getEmail());
        self::assertSame('Jane', $customer->getFirstName());
        self::assertSame('Doe', $customer->getLastName());
        self::assertSame('15551234567', $customer->getPhoneNumber());
    }

    public function test_it_returns_the_buyer_to_the_unplaced_checkout_when_the_total_grew_above_the_approved_amount(): void
    {
        $order = $this->cart();
        $originalPaymentId = $order->getLastPayment()?->getId();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order, approvedTotal: 1));

        $orderId = $order->getId();
        $content = $this->completeExpressCheckout($orderId);
        $order = $this->refreshOrder($orderId);

        self::assertSame($this->generateUrl('sylius_shop_checkout_complete'), $content['return_url']);
        self::assertNotSame('completed', $order->getCheckoutState());
        self::assertNotSame($originalPaymentId, $order->getLastPayment(PaymentInterface::STATE_CART)?->getId());
        self::assertSame([], $this->paymentRequests());
        self::assertSame(['sylius_paypal.order_total_changed'], $this->flashes()['error'] ?? []);
    }

    public function test_it_lowers_the_approved_amount_and_places_the_order_when_the_total_dropped(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order, approvedTotal: 99900));
        $this->payPalHttpClient()->addExpectation('PATCH', 'v2/checkout/orders/PAYPAL_ORDER_ID', [], 204);
        $this->mockCapture($this->total($order));

        $orderId = $order->getId();
        $content = $this->completeExpressCheckout($orderId);
        $order = $this->refreshOrder($orderId);

        self::assertSame($this->payUrl($this->capturePaymentRequest($order)), $content['return_url']);
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
    }

    public function test_it_returns_the_buyer_to_the_unplaced_checkout_when_paypal_refuses_to_lower_the_approved_amount(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order, approvedTotal: 99900));
        $this->payPalHttpClient()->addExpectation('PATCH', 'v2/checkout/orders/PAYPAL_ORDER_ID', ['name' => 'UNPROCESSABLE_ENTITY', 'debug_id' => 'DEBUG_ID'], 422);

        $orderId = $order->getId();
        $content = $this->completeExpressCheckout($orderId);

        self::assertSame($this->generateUrl('sylius_shop_checkout_complete'), $content['return_url']);
        self::assertNotSame('completed', $this->refreshOrder($orderId)->getCheckoutState());
        self::assertSame([], $this->paymentRequests());
        self::assertSame(['sylius_paypal.order_total_changed'], $this->flashes()['error'] ?? []);
    }

    public function test_it_refuses_the_request_when_the_paypal_order_id_does_not_match_the_payment(): void
    {
        $order = $this->cart();

        $orderId = $order->getId();
        $content = $this->completeExpressCheckout($orderId, 'OTHER_PAYPAL_ORDER_ID');
        $order = $this->refreshOrder($orderId);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame($this->generateUrl('sylius_shop_checkout_complete'), $content['return_url']);
        self::assertSame('shipping_selected', $order->getCheckoutState());
        self::assertNull($order->getShippingAddress());
    }

    public function test_it_refuses_the_request_when_the_payment_carries_no_paypal_order_id(): void
    {
        $order = $this->cart();
        $payment = $order->getLastPayment();
        $payment?->setDetails([]);
        $this->getEntityManager()->flush();

        $orderId = $order->getId();
        $this->completeExpressCheckout($orderId);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame('shipping_selected', $this->refreshOrder($orderId)->getCheckoutState());
    }

    public function test_it_returns_the_buyer_to_the_thank_you_page_when_the_order_is_already_placed(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_order'];

        $this->seedSession(['sylius_order_id' => $order->getId()]);
        $content = $this->completeExpressCheckout((int) $order->getId());

        self::assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);
    }

    public function test_it_returns_not_found_when_the_order_does_not_belong_to_the_caller(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        $this->completeExpressCheckout((int) $order->getId());

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function test_it_applies_the_shipping_method_the_buyer_picked_in_the_wallet(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails(
            $order,
            shippingOptions: [
                ['id' => 'STANDARD', 'amount' => ['currency_code' => 'USD', 'value' => '5.00'], 'selected' => false],
                ['id' => 'EXPRESS', 'amount' => ['currency_code' => 'USD', 'value' => '20.00'], 'selected' => true],
            ],
            approvedTotal: $this->total($order, self::EXPRESS_SHIPPING_COST),
        ));
        $this->mockCapture($this->total($order, self::EXPRESS_SHIPPING_COST));

        $orderId = $order->getId();
        $this->completeExpressCheckout($orderId);
        $order = $this->refreshOrder($orderId);

        /** @var ShipmentInterface $shipment */
        $shipment = $order->getShipments()->first();
        self::assertSame('EXPRESS', $shipment->getMethod()?->getCode());
        self::assertSame($this->total($order, self::EXPRESS_SHIPPING_COST), $order->getTotal());
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
    }

    public function test_it_keeps_the_default_shipping_method_when_the_wallet_sends_no_options(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order));
        $this->mockCapture($this->total($order));

        $orderId = $order->getId();
        $this->completeExpressCheckout($orderId);
        $order = $this->refreshOrder($orderId);

        /** @var ShipmentInterface $shipment */
        $shipment = $order->getShipments()->first();
        self::assertSame('STANDARD', $shipment->getMethod()?->getCode());
        self::assertSame(self::STANDARD_SHIPPING_COST, $order->getShippingTotal());
    }

    public function test_it_stores_the_region_of_the_paypal_shipping_address_as_a_province_code(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order, adminArea1: 'TX'));
        $this->mockCapture($this->total($order));

        $orderId = $order->getId();
        $this->completeExpressCheckout($orderId);

        $shippingAddress = $this->refreshOrder($orderId)->getShippingAddress();
        self::assertSame('US-TX', $shippingAddress?->getProvinceCode());
        self::assertNull($shippingAddress?->getProvinceName());
    }

    public function test_it_keeps_an_unknown_region_as_a_province_name(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order, adminArea1: 'Nowhere County'));
        $this->mockCapture($this->total($order));

        $orderId = $order->getId();
        $this->completeExpressCheckout($orderId);

        $shippingAddress = $this->refreshOrder($orderId)->getShippingAddress();
        self::assertNull($shippingAddress?->getProvinceCode());
        self::assertSame('Nowhere County', $shippingAddress?->getProvinceName());
    }

    public function test_it_builds_both_addresses_from_what_the_buyer_picked_in_the_wallet(): void
    {
        $order = $this->cart();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order));
        $this->mockCapture($this->total($order));

        $orderId = $order->getId();
        $this->completeExpressCheckout($orderId);
        $order = $this->refreshOrder($orderId);

        $shippingAddress = $order->getShippingAddress();
        self::assertNotNull($shippingAddress);
        self::assertSame('Oliver', $shippingAddress->getFirstName());
        self::assertSame('Queen', $shippingAddress->getLastName());
        self::assertSame('1 Star City Plaza', $shippingAddress->getStreet());
        self::assertSame('Star City', $shippingAddress->getCity());
        self::assertSame('10001', $shippingAddress->getPostcode());
        self::assertSame('US', $shippingAddress->getCountryCode());
        self::assertSame('15551234567', $shippingAddress->getPhoneNumber());

        $billingAddress = $order->getBillingAddress();
        self::assertNotNull($billingAddress);
        self::assertNotSame($shippingAddress->getId(), $billingAddress->getId());
        self::assertSame('1 Star City Plaza', $billingAddress->getStreet());
    }

    public function test_it_keeps_the_addresses_the_buyer_entered_in_checkout(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/addressed_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['addressed_cart'];
        $this->seedCurrentCart($order);
        $shippingAddressId = $order->getShippingAddress()?->getId();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->orderDetails($order));
        $this->mockCapture($this->total($order));

        $orderId = $order->getId();
        $this->completeExpressCheckout($orderId);

        $shippingAddress = $this->refreshOrder($orderId)->getShippingAddress();
        self::assertSame($shippingAddressId, $shippingAddress?->getId());
        self::assertSame('1 Main St', $shippingAddress?->getStreet());
        self::assertSame('US-TX', $shippingAddress?->getProvinceCode());
    }

    public function test_it_places_and_captures_an_order_the_buyer_pays_for_on_the_select_payment_step(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/addressed_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['addressed_cart'];
        $this->seedCurrentCart($order);
        $orderId = $order->getId();

        $this->payPalApi()->mockCreateOrder('SELECT_PAYMENT_ORDER_ID');
        $this->client->request('POST', '/en_US/create-pay-pal-order-from-cart/' . $orderId);
        self::assertSame('SELECT_PAYMENT_ORDER_ID', json_decode((string) $this->client->getResponse()->getContent(), true)['orderId']);
        self::assertSame([], $this->paymentRequests());

        $this->payPalApi()->mockOrderDetails('SELECT_PAYMENT_ORDER_ID', $this->orderDetails($order));
        $this->mockCapture($this->total($order), 'SELECT_PAYMENT_ORDER_ID');

        $content = $this->completeExpressCheckout($orderId, 'SELECT_PAYMENT_ORDER_ID');
        $order = $this->refreshOrder($orderId);

        $paymentRequest = $this->capturePaymentRequest($order);
        self::assertSame($this->payUrl($paymentRequest), $content['return_url']);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame('completed', $order->getCheckoutState());
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
        self::assertSame('1 Main St', $order->getShippingAddress()?->getStreet());
    }

    private function cart(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        $this->seedCurrentCart($order);

        return $order;
    }

    private function authorizeWith(OrderInterface $order): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $order->getLastPayment()?->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['use_authorize' => true]));

        $this->getEntityManager()->flush();
    }

    private function total(OrderInterface $order, int $shippingCost = self::STANDARD_SHIPPING_COST): int
    {
        $quantity = 0;
        foreach ($order->getItems() as $item) {
            $quantity += $item->getQuantity();
        }

        return $quantity * self::MUG_PRICE + $shippingCost;
    }

    private function mockCapture(int $capturedTotal, string $payPalOrderId = 'PAYPAL_ORDER_ID'): void
    {
        $this->payPalApi()->mockOrderDetails($payPalOrderId);
        $this->payPalApi()->mockUpdateOrderAddress($payPalOrderId);
        $this->payPalApi()->mockCapture($payPalOrderId);
        $this->payPalApi()->mockOrderDetailsWithCapture($payPalOrderId, value: number_format($capturedTotal / 100, 2, '.', ''));
    }

    /**
     * @param array<int, array<string, mixed>> $shippingOptions
     *
     * @return array<string, mixed>
     */
    private function orderDetails(
        OrderInterface $order,
        array $shippingOptions = [],
        ?string $adminArea1 = null,
        ?int $approvedTotal = null,
    ): array {
        $approvedTotal ??= $this->total($order);
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
                'reference_id' => 'REFERENCE_ID',
                'amount' => ['currency_code' => 'USD', 'value' => number_format($approvedTotal / 100, 2, '.', '')],
                'shipping' => $shipping,
            ]],
        ];
    }

    private function seedCurrentCart(OrderInterface $order): void
    {
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        self::getContainer()->get('request_stack')->push(new Request());
        self::getContainer()->get('request_stack')->getCurrentRequest()->setSession($session);
        self::getContainer()->get(CartStorageInterface::class)->setForChannel($order->getChannel(), $order);
        $session->save();
        self::getContainer()->get('request_stack')->pop();

        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    /** @param array<string, mixed> $values */
    private function seedSession(array $values): void
    {
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        foreach ($values as $name => $value) {
            $session->set($name, $value);
        }
        $session->save();

        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    /** @return array<string, mixed> */
    private function completeExpressCheckout(int $orderId, string $payPalOrderId = 'PAYPAL_ORDER_ID'): array
    {
        $this->client->request(
            'POST',
            '/en_US/paypal/express-checkout/complete',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['payPalOrderId' => $payPalOrderId, 'orderId' => $orderId]),
        );

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function refreshOrder(int $orderId): OrderInterface
    {
        $this->getEntityManager()->clear();

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        return $order;
    }

    private function capturePaymentRequest(OrderInterface $order): PaymentRequestInterface
    {
        $paymentRequests = $this->paymentRequests();
        self::assertCount(1, $paymentRequests);
        self::assertSame(PaymentRequestInterface::ACTION_CAPTURE, $paymentRequests[0]->getAction());
        self::assertSame($order->getLastPayment()?->getId(), $paymentRequests[0]->getPayment()->getId());

        return $paymentRequests[0];
    }

    /** @return list<PaymentRequestInterface> */
    private function paymentRequests(): array
    {
        return array_values(self::getContainer()->get('sylius.repository.payment_request')->findAll());
    }

    /** @return array<string, list<string>> */
    private function flashes(): array
    {
        return $this->client->getRequest()->getSession()->getBag('flashes')->peekAll();
    }

    private function payUrl(PaymentRequestInterface $paymentRequest): string
    {
        return $this->generateUrl('sylius_shop_payment_request_pay', ['hash' => $paymentRequest->getId(), '_locale' => 'en_US']);
    }

    /** @param array<string, mixed> $parameters */
    private function generateUrl(string $route, array $parameters = []): string
    {
        /** @var UrlGeneratorInterface $router */
        $router = self::getContainer()->get('router');

        return $router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
