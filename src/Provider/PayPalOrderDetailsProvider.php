<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use GuzzleHttp\Client;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;

final class PayPalOrderDetailsProvider implements PayPalOrderDetailsProviderInterface
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function provide(string $clientId, string $clientSecret, string $orderId): array
    {
        $authResponse = $this->client->request(
            'POST',
            'https://api.sandbox.paypal.com/v1/oauth2/token',
            [
                'auth' => [$clientId, $clientSecret],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]
        );

        if ($authResponse->getStatusCode() !== 200) {
            throw new PayPalAuthorizationException();
        }

        $content = (array) json_decode($authResponse->getBody()->getContents(), true);

        $response = $this->client->request(
            'GET',
            'https://api.sandbox.paypal.com/v2/checkout/orders/' . $orderId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . (string) $content['access_token'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'sylius-ppcp4p-bn-code',
                ],
            ]
        );

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
