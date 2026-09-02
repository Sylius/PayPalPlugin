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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Provider\PayPalHostProviderInterface;

final class WebhookApiTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private PayPalHostProviderInterface&MockObject $hostProvider;

    private RequestFactoryInterface&MockObject $requestFactory;

    private StreamFactoryInterface&MockObject $streamFactory;

    private WebhookApi $webhookApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->hostProvider = $this->createMock(PayPalHostProviderInterface::class);
        $this->hostProvider->method('getApiBaseUrl')->willReturn('http://base-url.com/');
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);

        $this->webhookApi = new WebhookApi(
            $this->client,
            $this->hostProvider,
            $this->requestFactory,
            $this->streamFactory,
        );
    }

    #[Test]
    public function it_registers_webhook(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('POST', 'http://base-url.com/v1/notifications/webhooks')
            ->willReturn($request);

        $request
            ->method('withHeader')
            ->willReturn($request);

        $request
            ->method('withBody')
            ->willReturn($request);

        $this->streamFactory
            ->method('createStream')
            ->willReturn($stream);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::once())
            ->method('getContents')
            ->willReturn('{ "status": "CREATED" }');

        $result = $this->webhookApi->register('TOKEN', 'https://webhook.com');

        self::assertEquals(['status' => 'CREATED'], $result);
    }

    #[Test]
    public function it_registers_webhook_without_https(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('POST', 'http://base-url.com/v1/notifications/webhooks')
            ->willReturn($request);

        $request
            ->method('withHeader')
            ->willReturn($request);

        $request
            ->method('withBody')
            ->willReturn($request);

        $this->streamFactory
            ->method('createStream')
            ->willReturn($stream);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::once())
            ->method('getContents')
            ->willReturn('{ "status": "CREATED" }');

        $result = $this->webhookApi->register('TOKEN', 'http://webhook.com');

        self::assertEquals(['status' => 'CREATED'], $result);
    }
}
