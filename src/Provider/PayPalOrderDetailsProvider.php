<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use GuzzleHttp\Client;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;

final class PayPalOrderDetailsProvider implements PayPalOrderDetailsProviderInterface
{
    /** @var Client */
    private $client;

    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    public function __construct(Client $client, AuthorizeClientApiInterface $authorizeClientApi)
    {
        $this->client = $client;
        $this->authorizeClientApi = $authorizeClientApi;
    }

    public function provide(string $clientId, string $clientSecret, string $orderId): array
    {
        $token = $this->authorizeClientApi->authorize($clientId, $clientSecret);

        $response = $this->client->request(
            'GET',
            'https://api.sandbox.paypal.com/v2/checkout/orders/' . $orderId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'sylius-ppcp4p-bn-code',
                ],
            ]
        );

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
