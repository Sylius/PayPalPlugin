<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Api;

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
        RequestInterface $request
    ): void {
        $this->beConstructedWith($client, $requestFactory, $streamFactory, 'http://base-url.com/');
        $request->withHeader(Argument::any(), Argument::any())->willReturn($request);
        $request->withBody(Argument::any())->willReturn($request);
        $streamFactory->createStream(Argument::any())->willReturn($stream);
    }

    function it_registers_webhook(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        RequestInterface $request,
        ResponseInterface $response,
        StreamInterface $body
    ): void {

        $requestFactory->createRequest('POST','http://base-url.com/v1/notifications/webhooks')
            ->willReturn($request);
        $client->sendRequest($request)->willReturn($response);

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "status": "CREATED" }');

        $this->register('TOKEN', 'https://webhook.com')->shouldReturn(['status' => 'CREATED']);
    }

    function it_registers_webhook_without_https(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        RequestInterface $request,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $requestFactory->createRequest('POST','http://base-url.com/v1/notifications/webhooks')
            ->willReturn($request);
        $client->sendRequest($request)->willReturn($response);

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{ "status": "CREATED" }');

        $this->register('TOKEN', 'http://webhook.com')->shouldReturn(['status' => 'CREATED']);
    }
}
