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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\PayPalPlugin\Api\UpdateWebhookApi;
use Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface;

final class UpdateWebhookApiTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private RequestFactoryInterface&MockObject $requestFactory;

    private StreamFactoryInterface&MockObject $streamFactory;

    private UpdateWebhookApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);

        $this->api = new UpdateWebhookApi(
            $this->client,
            'https://api-m.sandbox.paypal.com/',
            $this->requestFactory,
            $this->streamFactory,
        );
    }

    public function test_it_implements_update_webhook_api_interface(): void
    {
        self::assertInstanceOf(UpdateWebhookApiInterface::class, $this->api);
    }

    public function test_it_replaces_the_event_types_of_an_existing_webhook(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{"id": "WEBHOOK_ID"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($body);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('PATCH', 'https://api-m.sandbox.paypal.com/v1/notifications/webhooks/WEBHOOK_ID')
            ->willReturn($request)
        ;

        $payload = null;
        $this->streamFactory
            ->method('createStream')
            ->willReturnCallback(function (string $content) use (&$payload, $body): StreamInterface {
                $payload = json_decode($content, true);

                return $body;
            })
        ;

        $this->client->method('sendRequest')->willReturn($response);

        self::assertSame(['id' => 'WEBHOOK_ID'], $this->api->updateEventTypes('TOKEN', 'WEBHOOK_ID', ['A', 'B']));
        self::assertSame([
            [
                'op' => 'replace',
                'path' => '/event_types',
                'value' => [['name' => 'A'], ['name' => 'B']],
            ],
        ], $payload);
    }
}
