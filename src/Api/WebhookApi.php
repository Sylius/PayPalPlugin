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

final readonly class WebhookApi implements WebhookApiInterface
{
    /** @var list<string> */
    public const EVENT_TYPES = [
        'PAYMENT.CAPTURE.REFUNDED',
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.DENIED',
        'PAYMENT.CAPTURE.DECLINED',
        'PAYMENT.CAPTURE.PENDING',
    ];

    public function __construct(
        private ClientInterface $client,
        private string $baseUrl,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
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
                        'event_types' => array_map(
                            static fn (string $eventType): array => ['name' => $eventType],
                            self::EVENT_TYPES,
                        ),
                    ],
                ),
            ),
        );

        $response = $this->client->sendRequest($request);

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
