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
    public function mockOrderDetails(string $payPalOrderId, array $order = []): void
    {
        $this->client->addExpectation('GET', 'v2/checkout/orders/' . $payPalOrderId, array_merge([
            'id' => $payPalOrderId,
            'status' => 'APPROVED',
        ], $order));
    }
}
