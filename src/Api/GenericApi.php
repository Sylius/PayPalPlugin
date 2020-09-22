<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GenericApi implements GenericApiInterface
{
    /** @var HttpClientInterface */
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function get(string $token, string $url): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        return $this->client->request('GET', $url, $options)->toArray();
    }
}
