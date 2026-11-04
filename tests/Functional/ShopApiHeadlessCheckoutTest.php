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
use PHPUnit\Framework\Attributes\Before;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;

final class ShopApiHeadlessCheckoutTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    private const ORDER_TOTAL = '5.20';

    private const PAYER_ACTION_URL = 'https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID';

    #[Before]
    public function setUpDatabase(): void
    {
        self::ensureSharedKernelShutdown();
        self::createSharedKernel();

        parent::setUpDatabase();
    }

    public function test_it_pays_an_order_placed_through_the_shop_api_with_paypal(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml']);
        [$token, $paymentId] = $this->checkOut();

        $this->client->request('GET', sprintf('/api/v2/shop/orders/%s/payments/%s/configuration', $token, $paymentId), server: $this->headers());
        self::assertResponseIsSuccessful();
        $configuration = $this->responseContent();
        self::assertSame('https://www.paypal.com/web-sdk/v6/core', $configuration['webSdkScriptUrl']);
        self::assertSame('CLIENT_ID', $configuration['webSdkInstanceConfig']['clientId']);
        self::assertSame(self::ORDER_TOTAL, $configuration['amount']);
        self::assertSame(['paypal', 'card'], $configuration['paymentSources']);

        $this->payPalApi()->mockCreateOrder();
        $paymentRequest = $this->requestPayment($token, $paymentId, 'paypal');
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $paymentRequest['state']);
        self::assertSame('PAYPAL_ORDER_ID', $paymentRequest['responseData']['paypal_order_id']);

        $this->mockCapture($paymentRequest['hash']);
        $paymentRequest = $this->completePayment($paymentRequest['hash']);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest['state']);
        self::assertSame(OrderPaymentStates::STATE_PAID, $this->orderPaymentState($token));
    }

    public function test_it_pays_with_paypal_once_the_payer_comes_back_after_cancelling(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml']);
        [$token, $paymentId] = $this->checkOut();

        $this->payPalApi()->mockCreateOrder();
        $cancelledPaymentRequest = $this->requestPayment($token, $paymentId, 'paypal');
        $this->payPalApi()->mockUnapprovedOrderDetails();
        $cancelledPaymentRequest = $this->completePayment($cancelledPaymentRequest['hash']);
        self::assertSame(PaymentRequestInterface::STATE_CANCELLED, $cancelledPaymentRequest['state']);

        $this->payPalApi()->mockCreateOrder();
        $paymentRequest = $this->requestPayment($token, $paymentId, 'paypal');
        $this->mockCapture($paymentRequest['hash']);
        $paymentRequest = $this->completePayment($paymentRequest['hash']);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest['state']);
        self::assertSame(OrderPaymentStates::STATE_PAID, $this->orderPaymentState($token));
    }

    public function test_it_pays_with_a_card_the_payer_authenticated_with_3d_secure(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml']);
        [$token, $paymentId] = $this->checkOut();

        $this->payPalApi()->mockCreateOrder();
        $paymentRequest = $this->requestPayment($token, $paymentId, 'card');
        $this->payPalApi()->mockCardOrderDetails();
        $this->mockCapture($paymentRequest['hash']);
        $paymentRequest = $this->completePayment($paymentRequest['hash']);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest['state']);
        self::assertSame(OrderPaymentStates::STATE_PAID, $this->orderPaymentState($token));
    }

    public function test_it_pays_with_the_new_payment_of_the_order_after_3d_secure_declined_the_card(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml']);
        [$token, $paymentId] = $this->checkOut();

        $this->payPalApi()->mockCreateOrder();
        $failedPaymentRequest = $this->requestPayment($token, $paymentId, 'card');
        $this->payPalApi()->mockCardOrderDetails(authenticationStatus: 'N');
        $failedPaymentRequest = $this->completePayment($failedPaymentRequest['hash']);

        self::assertSame(PaymentRequestInterface::STATE_FAILED, $failedPaymentRequest['state']);
        self::assertSame('declined', $failedPaymentRequest['responseData']['three_d_secure']);
        self::assertNotSame(OrderPaymentStates::STATE_PAID, $this->orderPaymentState($token));

        $newPaymentId = $this->lastPaymentId($token);
        self::assertNotSame($paymentId, $newPaymentId);

        $this->payPalApi()->mockCreateOrder();
        $paymentRequest = $this->requestPayment($token, $newPaymentId, 'paypal');
        $this->mockCapture($paymentRequest['hash']);
        $paymentRequest = $this->completePayment($paymentRequest['hash']);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest['state']);
        self::assertSame(OrderPaymentStates::STATE_PAID, $this->orderPaymentState($token));
    }

    public function test_it_pays_with_trustly_once_the_payer_is_back_from_the_bank(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml']);
        $this->configurePayPal(['trustly_enabled' => true]);
        [$token, $paymentId] = $this->checkOut();

        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID', [
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [['href' => self::PAYER_ACTION_URL, 'rel' => 'payer-action', 'method' => 'GET']],
        ]);
        $paymentRequest = $this->requestPayment($token, $paymentId, 'trustly');
        self::assertSame(self::PAYER_ACTION_URL, $paymentRequest['responseData']['payer_action_url']);
        self::assertSame(
            sprintf('https://headless.example.com/paypal/return/%s', $paymentRequest['hash']),
            $this->createOrderRequestBody()['payment_source']['trustly']['experience_context']['return_url'],
        );

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', ['status' => 'PAYER_ACTION_REQUIRED']);
        $paymentRequest = $this->completePayment($paymentRequest['hash']);
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $paymentRequest['state']);
        self::assertTrue($paymentRequest['responseData']['awaiting_payer']);

        $this->payPalApi()->mockOrderDetailsWithCapture(value: self::ORDER_TOTAL);
        $paymentRequest = $this->completePayment($paymentRequest['hash']);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest['state']);
        self::assertSame(OrderPaymentStates::STATE_PAID, $this->orderPaymentState($token));
    }

    public function test_it_authorizes_a_paypal_payment_of_a_payment_method_that_authorizes_payments(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml']);
        $this->configurePayPal(['use_authorize' => true]);
        [$token, $paymentId] = $this->checkOut();

        $this->payPalApi()->mockCreateOrder();
        $paymentRequest = $this->requestPayment($token, $paymentId, 'paypal', PaymentRequestInterface::ACTION_AUTHORIZE);
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockAuthorize(requestId: $paymentRequest['hash'] . '-authorize');
        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $paymentRequest = $this->completePayment($paymentRequest['hash']);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest['state']);
        self::assertSame(OrderPaymentStates::STATE_AUTHORIZED, $this->orderPaymentState($token));
    }

    /** @return array{string, int} */
    private function checkOut(): array
    {
        $this->client->request('POST', '/api/v2/shop/orders', server: $this->headers(), content: $this->json(['localeCode' => 'en_US']));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $token = $this->responseContent()['tokenValue'];

        $this->client->request('POST', sprintf('/api/v2/shop/orders/%s/items', $token), server: $this->headers(), content: $this->json([
            'productVariant' => '/api/v2/shop/product-variants/MUG_SW',
            'quantity' => 1,
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $address = [
            'firstName' => 'Oliver',
            'lastName' => 'Queen',
            'street' => '1 Main Street',
            'city' => 'Austin',
            'postcode' => '73301',
            'countryCode' => 'US',
            'provinceCode' => 'US-TX',
        ];
        $this->client->request('PUT', sprintf('/api/v2/shop/orders/%s', $token), server: $this->headers(), content: $this->json([
            'email' => 'oliver.queen@star-city.com',
            'billingAddress' => $address,
            'shippingAddress' => $address,
        ]));
        self::assertResponseIsSuccessful();
        $order = $this->responseContent();

        $this->client->request(
            'PATCH',
            sprintf('/api/v2/shop/orders/%s/shipments/%s', $token, $order['shipments'][0]['id']),
            server: $this->headers('application/merge-patch+json'),
            content: $this->json(['shippingMethod' => '/api/v2/shop/shipping-methods/STANDARD']),
        );
        self::assertResponseIsSuccessful();

        $paymentId = $order['payments'][0]['id'];
        $this->client->request(
            'PATCH',
            sprintf('/api/v2/shop/orders/%s/payments/%s', $token, $paymentId),
            server: $this->headers('application/merge-patch+json'),
            content: $this->json(['paymentMethod' => '/api/v2/shop/payment-methods/PAYPAL']),
        );
        self::assertResponseIsSuccessful();

        $this->client->request('PATCH', sprintf('/api/v2/shop/orders/%s/complete', $token), server: $this->headers('application/merge-patch+json'), content: $this->json([]));
        self::assertResponseIsSuccessful();

        return [$token, $paymentId];
    }

    /** @return array<string, mixed> */
    private function requestPayment(string $token, int $paymentId, string $paymentSource, ?string $action = null): array
    {
        $this->client->request('POST', sprintf('/api/v2/shop/orders/%s/payment-requests', $token), server: $this->headers(), content: $this->json(array_filter([
            'paymentId' => $paymentId,
            'paymentMethodCode' => 'PAYPAL',
            'action' => $action,
            'payload' => ['payment_source' => $paymentSource],
        ])));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->responseContent();
    }

    /** @return array<string, mixed> */
    private function completePayment(string $hash): array
    {
        $this->client->request('PUT', sprintf('/api/v2/shop/payment-requests/%s', $hash), server: $this->headers(), content: $this->json([]));
        self::assertResponseIsSuccessful();

        return $this->responseContent();
    }

    private function orderPaymentState(string $token): string
    {
        $this->client->request('GET', sprintf('/api/v2/shop/orders/%s', $token), server: $this->headers());
        self::assertResponseIsSuccessful();

        return $this->responseContent()['paymentState'];
    }

    private function lastPaymentId(string $token): int
    {
        $this->client->request('GET', sprintf('/api/v2/shop/orders/%s', $token), server: $this->headers());
        self::assertResponseIsSuccessful();
        $payments = $this->responseContent()['payments'];

        return end($payments)['id'];
    }

    private function mockCapture(string $hash): void
    {
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockCapture(requestId: $hash . '-capture');
        $this->payPalApi()->mockOrderDetailsWithCapture(value: self::ORDER_TOTAL);
    }

    /** @param array<string, mixed> $config */
    private function configurePayPal(array $config): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->get('sylius.repository.payment_method')->findOneBy(['code' => 'PAYPAL']);
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), $config));
        $this->getEntityManager()->flush();
    }

    /** @return array<string, mixed> */
    private function createOrderRequestBody(): array
    {
        foreach ($this->payPalHttpClient()->getSentRequests() as $sentRequest) {
            if ('POST' === $sentRequest['method'] && '/v2/checkout/orders' === $sentRequest['path']) {
                return $sentRequest['body'];
            }
        }

        self::fail('The PayPal order was not created.');
    }

    /** @return array<string, string> */
    private function headers(string $contentType = 'application/ld+json'): array
    {
        return ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/ld+json'];
    }

    /** @param array<string, mixed> $content */
    private function json(array $content): string
    {
        return json_encode((object) $content, \JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function responseContent(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
