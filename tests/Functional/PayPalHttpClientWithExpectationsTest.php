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

use Nyholm\Psr7\Factory\Psr17Factory;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApi;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalApiMocker;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalHttpClientWithExpectations;

final class PayPalHttpClientWithExpectationsTest extends KernelTestCase
{
    private PayPalHttpClientWithExpectations $httpClient;

    private PayPalApiMocker $mocker;

    private PayPalClientInterface $payPalClient;

    private PaymentMethodInterface $paymentMethod;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->httpClient = self::getContainer()->get(PayPalHttpClientWithExpectations::class);
        $this->mocker = self::getContainer()->get(PayPalApiMocker::class);
        $this->payPalClient = self::getContainer()->get('sylius_paypal.client.paypal');

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);
        $this->paymentMethod = new PaymentMethod();
        $this->paymentMethod->setGatewayConfig($gatewayConfig);

        $this->httpClient->resetExpectations();
    }

    protected function tearDown(): void
    {
        $this->httpClient->resetExpectations();

        parent::tearDown();
    }

    public function test_it_answers_the_paypal_client_with_the_declared_responses_in_order(): void
    {
        $this->mocker->mockAccessToken('ACCESS_TOKEN');
        $this->mocker->mockOrderDetails('PAYPAL_ORDER_ID', ['status' => 'COMPLETED']);

        $authorizeClientApi = new AuthorizeClientApi(
            self::getContainer()->get('sylius_paypal.http_client.retrying'),
            new Psr17Factory(),
            new Psr17Factory(),
            self::getContainer()->get('sylius_paypal.provider.host'),
        );

        self::assertSame('ACCESS_TOKEN', $authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET', true)->value());
        self::assertSame(
            ['id' => 'PAYPAL_ORDER_ID', 'status' => 'COMPLETED'],
            $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/PAYPAL_ORDER_ID'),
        );
        self::assertFalse($this->httpClient->hasExpectations());
    }

    public function test_it_returns_the_declared_error_response(): void
    {
        $this->httpClient->addExpectation('GET', 'v2/checkout/orders/MISSING', ['name' => 'RESOURCE_NOT_FOUND', 'debug_id' => 'DEBUG_ID'], 404);

        self::assertSame(
            ['name' => 'RESOURCE_NOT_FOUND', 'debug_id' => 'DEBUG_ID'],
            $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/MISSING'),
        );
    }

    public function test_it_fails_to_connect_as_many_times_as_it_is_told(): void
    {
        $this->mocker->mockOrderDetailsUnreachable('PAYPAL_ORDER_ID', attempts: 3);

        $this->expectException(PayPalApiTimeoutException::class);

        try {
            $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/PAYPAL_ORDER_ID');
        } finally {
            self::assertFalse($this->httpClient->hasExpectations());
        }
    }

    public function test_it_rejects_a_request_nobody_declared(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No expectations found for the PayPal test request "GET /v2/checkout/orders/PAYPAL_ORDER_ID".');

        $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/PAYPAL_ORDER_ID');
    }

    public function test_it_rejects_a_request_other_than_the_declared_one(): void
    {
        $this->mocker->mockOrderDetails('PAYPAL_ORDER_ID');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected the PayPal request "GET /v2/checkout/orders/PAYPAL_ORDER_ID" but got "GET /v2/checkout/orders/OTHER_ORDER_ID".');

        $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/OTHER_ORDER_ID');
    }

    public function test_it_refuses_a_request_without_the_declared_header(): void
    {
        $this->httpClient->addExpectation('POST', 'v2/checkout/orders/PAYPAL_ORDER_ID/capture', [], 201, ['PayPal-Request-Id' => 'EXPECTED_REQUEST_ID']);

        $this->expectExceptionMessage('with the PayPal-Request-Id header "EXPECTED_REQUEST_ID" but got "OTHER_REQUEST_ID"');

        $this->payPalClient->post($this->paymentMethod, 'v2/checkout/orders/PAYPAL_ORDER_ID/capture', null, ['PayPal-Request-Id' => 'OTHER_REQUEST_ID']);
    }
}
