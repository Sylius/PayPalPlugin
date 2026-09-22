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

namespace Sylius\PayPalPlugin\Api;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class UpdateWebhookApi implements UpdateWebhookApiInterface
{
    public function __construct(
        private ClientInterface $client,
        private string $baseUrl,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function updateEventTypes(string $token, string $webhookId, array $eventTypes): array
    {
        $request = $this->requestFactory
            ->createRequest('PATCH', sprintf('%sv1/notifications/webhooks/%s', $this->baseUrl, $webhookId))
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
        ;

        $request = $request->withBody($this->streamFactory->createStream((string) json_encode([
            [
                'op' => 'replace',
                'path' => '/event_types',
                'value' => array_map(
                    static fn (string $eventType): array => ['name' => $eventType],
                    $eventTypes,
                ),
            ],
        ])));

        $response = $this->client->sendRequest($request);

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
