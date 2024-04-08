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

namespace spec\Sylius\PayPalPlugin\Api;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class WebhookApiSpec extends ObjectBehavior
{
    function let(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        StreamInterface $stream,
        RequestInterface $request,
    ): void {
        $this->beConstructedWith($client, 'http://base-url.com/', $requestFactory, $streamFactory);
        $request->withHeader(Argument::any(), Argument::any())->willReturn($request);
        $request->withBody(Argument::any())->willReturn($request);
        $streamFactory->createStream(Argument::any())->willReturn($stream);
    }

    function it_registers_webhook(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        RequestInterface $request,
        ResponseInterface $response,
        StreamInterface $body,
    ): void {
        $requestFactory
            ->createRequest('POST', 'http://base-url.com/v1/notifications/webhooks')
            ->willReturn($request);
        $client->sendRequest($request)->willReturn($response);

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "status": "CREATED" }');

        $this->register('TOKEN', 'https://webhook.com')->shouldReturn(['status' => 'CREATED']);
    }

    function it_registers_webhook_using_guzzle_client(
        GuzzleClientInterface $client,
        ResponseInterface $response,
        StreamInterface $body,
    ): void {
        $this->beConstructedWith($client, 'http://base-url.com/');

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
            ],
        )->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "status": "CREATED" }');

        $this->register('TOKEN', 'https://webhook.com')->shouldReturn(['status' => 'CREATED']);
    }

    function it_registers_webhook_without_https(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        RequestInterface $request,
        ResponseInterface $response,
        StreamInterface $body,
    ): void {
        $requestFactory->createRequest('POST', 'http://base-url.com/v1/notifications/webhooks')
            ->willReturn($request);
        $client->sendRequest($request)->willReturn($response);

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "status": "CREATED" }');

        $this->register('TOKEN', 'http://webhook.com')->shouldReturn(['status' => 'CREATED']);
    }

    function it_registers_webhook_without_https_using_guzzle_client(
        GuzzleClientInterface $client,
        ResponseInterface $response,
        StreamInterface $body,
    ): void {
        $this->beConstructedWith($client, 'http://base-url.com/');

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
            ],
        )->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "status": "CREATED" }');

        $this->register('TOKEN', 'http://webhook.com')->shouldReturn(['status' => 'CREATED']);
    }
}
