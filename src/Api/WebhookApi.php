<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;


use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class WebhookApi implements WebhookApiInterface
{
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private string $baseUrl;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $baseUrl
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUrl = $baseUrl;
    }

    public function register(string $token, string $webhookUrl): array
    {
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
                        ]
                    ]
                )
            )
        );

        $response = $this->client->sendRequest($request);

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
