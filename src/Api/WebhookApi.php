<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class WebhookApi implements WebhookApiInterface
{
    public function __construct(
        private readonly GuzzleClientInterface|ClientInterface $client,
        private readonly string $baseUrl,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
    ) {
        if ($this->client instanceof GuzzleClientInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Passing GuzzleHttp\ClientInterface as a first argument in the constructor is deprecated and will be prohibited in 2.0. Use Psr\Http\Client\ClientInterface instead.',
                self::class,
            );
        }

        if (null === $this->requestFactory && null === $this->streamFactory) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing $requestFactory and $streamFactory to %s constructor is deprecated and will be prohibited in 2.0',
                self::class,
            );
        }
    }

    public function register(string $token, string $webhookUrl): array
    {
        if ($this->client instanceof GuzzleClientInterface || null === $this->requestFactory || null === $this->streamFactory) {
            return $this->legacyRegister($token, $webhookUrl);
        }

        $request = $this->requestFactory->createRequest('POST', $this->baseUrl . 'v1/notifications/webhooks')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        $request = $request->withBody(
            $this->streamFactory->createStream(
                json_encode(
                    [
                        'url' => preg_replace('/^http:/i', 'https:', $webhookUrl),
                        'event_types' => [
                            ['name' => 'PAYMENT.CAPTURE.REFUNDED'],
                        ],
                    ],
                ),
            ),
        );

        $response = $this->client->sendRequest($request);

        return (array) json_decode($response->getBody()->getContents(), true);
    }

    private function legacyRegister(string $token, string $webhookUrl): array
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
