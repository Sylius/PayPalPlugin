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

namespace Tests\Sylius\PayPalPlugin\Behat\Mocker;

final readonly class PayPalApiMocker
{
    public function __construct(private PayPalHttpClientWithExpectations $client)
    {
    }

    public function mockAccessToken(string $accessToken = 'ACCESS_TOKEN'): void
    {
        $this->client->addExpectation('POST', 'v1/oauth2/token', [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 32400,
        ]);
    }

    /** @param array<string, mixed> $order */
    public function mockCreateOrder(string $payPalOrderId = 'PAYPAL_ORDER_ID', array $order = []): void
    {
        $this->client->addExpectation('POST', 'v2/checkout/orders', array_merge([
            'id' => $payPalOrderId,
            'status' => 'CREATED',
        ], $order));
    }

    public function mockCapture(string $payPalOrderId = 'PAYPAL_ORDER_ID'): void
    {
        $this->client->addExpectation('POST', sprintf('v2/checkout/orders/%s/capture', $payPalOrderId), [
            'id' => $payPalOrderId,
            'status' => 'COMPLETED',
        ], 201);
    }

    public function mockUpdateOrderAddress(string $payPalOrderId = 'PAYPAL_ORDER_ID'): void
    {
        $this->client->addExpectation('PATCH', 'v2/checkout/orders/' . $payPalOrderId, [], 204);
        $this->client->addExpectation('PATCH', 'v2/checkout/orders/' . $payPalOrderId, [], 204);
    }

    public function mockOrderDetailsWithCapture(
        string $payPalOrderId = 'PAYPAL_ORDER_ID',
        string $captureStatus = 'COMPLETED',
        string $value = '0.20',
        string $currencyCode = 'USD',
    ): void {
        $this->mockOrderDetails($payPalOrderId, [
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => 'REFERENCE_ID',
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE_ID',
                    'status' => $captureStatus,
                    'amount' => ['currency_code' => $currencyCode, 'value' => $value],
                ]]],
            ]],
        ]);
    }

    public function mockOrderDetailsNotFound(string $payPalOrderId = 'PAYPAL_ORDER_ID'): void
    {
        $this->client->addExpectation('GET', 'v2/checkout/orders/' . $payPalOrderId, ['name' => 'RESOURCE_NOT_FOUND', 'debug_id' => 'DEBUG_ID'], 404);
    }

    public function mockOrderDetailsUnreachable(string $payPalOrderId = 'PAYPAL_ORDER_ID', int $attempts = 5): void
    {
        for ($attempt = 0; $attempt < $attempts; ++$attempt) {
            $this->client->addConnectionFailure('GET', 'v2/checkout/orders/' . $payPalOrderId);
        }
    }

    /** @param array<string, mixed> $order */
    public function mockOrderDetails(string $payPalOrderId, array $order = []): void
    {
        $this->client->addExpectation('GET', 'v2/checkout/orders/' . $payPalOrderId, array_merge([
            'id' => $payPalOrderId,
            'status' => 'APPROVED',
        ], $order));
    }
}
