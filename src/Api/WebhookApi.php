<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use GuzzleHttp\ClientInterface;

final class WebhookApi implements WebhookApiInterface
{
    private ClientInterface $client;

    private string $baseUrl;

    public function __construct(ClientInterface $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function register(string $token, string $webhookUrl): array
    {
        $response = $this->client->request('POST', $this->baseUrl . 'v1/notifications/webhooks', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'url' => preg_replace('/^http:/i', 'https:', $webhookUrl),
                'event_types' => [
                    ['name' => 'PAYMENT.CAPTURE.REFUNDED'],
                ],
            ],
        ]);

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
