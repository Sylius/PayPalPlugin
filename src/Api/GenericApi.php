<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use GuzzleHttp\ClientInterface;

final class GenericApi implements GenericApiInterface
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function get(string $token, string $url): array
    {
        $response = $this->client->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
