<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Api;

use GuzzleHttp\ClientInterface;
use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class WebhookApiSpec extends ObjectBehavior
{
    function let(ClientInterface $client): void
    {
        $this->beConstructedWith($client, 'http://base-url.com/');
    }

    function it_registers_webhook(
        ClientInterface $client,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $client->request(
            'POST',
            'http://base-url.com/v1/notifications/webhooks',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'url' => 'https://webhook.com',
                    'event_types' => [
                        ['name' => 'PAYMENT.CAPTURE.REFUNDED'],
                    ],
                ],
            ]
        )->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "status": "CREATED" }');

        $this->register('TOKEN', 'https://webhook.com')->shouldReturn(['status' => 'CREATED']);
    }

    function it_registers_webhook_without_https(
        ClientInterface $client,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $client->request(
            'POST',
            'http://base-url.com/v1/notifications/webhooks',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'url' => 'https://webhook.com',
                    'event_types' => [
                        ['name' => 'PAYMENT.CAPTURE.REFUNDED'],
                    ],
                ],
            ]
        )->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "status": "CREATED" }');

        $this->register('TOKEN', 'http://webhook.com')->shouldReturn(['status' => 'CREATED']);
    }
}
