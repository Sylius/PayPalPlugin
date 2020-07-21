<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use GuzzleHttp\Client;

final class OrderDetailsApi implements OrderDetailsApiInterface
{
    /** @var Client */
    private $client;

    /** @var string */
    private $baseUrl;

    public function __construct(Client $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function get(string $token, string $orderId): array
    {
        $response = $this->client->request(
            'GET',
            sprintf('%sv2/checkout/orders/%s', $this->baseUrl, $orderId), [
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
