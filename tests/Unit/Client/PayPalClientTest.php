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

namespace Tests\Sylius\PayPalPlugin\Unit\Client;

use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\PayPalPlugin\Client\PayPalClient;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalHostProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class PayPalClientTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private LoggerInterface&MockObject $logger;

    private UuidProviderInterface&MockObject $uuidProvider;

    private PayPalConfigurationProviderInterface&MockObject $payPalConfigurationProvider;

    private ChannelContextInterface&MockObject $channelContext;

    private RequestFactoryInterface&MockObject $requestFactory;

    private StreamFactoryInterface&MockObject $streamFactory;

    private PayPalHostProviderInterface&MockObject $hostProvider;

    private PayPalClient $payPalClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->uuidProvider = $this->createMock(UuidProviderInterface::class);
        $this->payPalConfigurationProvider = $this->createMock(PayPalConfigurationProviderInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->hostProvider = $this->createMock(PayPalHostProviderInterface::class);
        $this->hostProvider->method('getApiBaseUrl')->willReturn('https://test-api.paypal.com/');

        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);

        $this->payPalClient = new PayPalClient(
            $this->client,
            $this->logger,
            $this->uuidProvider,
            $this->payPalConfigurationProvider,
            $this->channelContext,
            $this->hostProvider,
            5,
            $this->requestFactory,
            $this->streamFactory,
            false,
        );
    }

    #[Test]
    public function it_implements_paypal_client_interface(): void
    {
        self::assertInstanceOf(PayPalClientInterface::class, $this->payPalClient);
    }

    #[Test]
    public function it_returns_auth_token_for_given_client_data(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('POST', 'https://test-api.paypal.com/v1/oauth2/token')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response
            ->expects(self::once())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::once())
            ->method('getContents')
            ->willReturn('{"access_token": "TOKEN"}');

        $result = $this->payPalClient->authorize('CLIENT_ID', 'CLIENT_SECRET');

        self::assertEquals(['access_token' => 'TOKEN'], $result);
    }

    #[Test]
    public function it_throws_an_exception_if_client_could_not_be_authorized(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('POST', 'https://test-api.paypal.com/v1/oauth2/token')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(401);

        $this->expectException(PayPalAuthorizationException::class);

        $this->payPalClient->authorize('CLIENT_ID', 'CLIENT_SECRET');
    }

    #[Test]
    public function it_calls_get_request_on_paypal_api(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $this->channelContext
            ->method('getChannel')
            ->willReturn($channel);

        $this->payPalConfigurationProvider
            ->expects(self::once())
            ->method('getPartnerAttributionId')
            ->with($channel)
            ->willReturn('TRACKING-ID');

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'https://test-api.paypal.com/v2/get-request/')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response
            ->expects(self::once())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::once())
            ->method('getContents')
            ->willReturn('{"status": "OK", "id": "123123"}');

        $result = $this->payPalClient->get('v2/get-request/', 'TOKEN');

        self::assertEquals(['status' => 'OK', 'id' => '123123'], $result);
    }

    #[Test]
    public function it_calls_post_request_on_paypal_api(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $this->channelContext
            ->method('getChannel')
            ->willReturn($channel);

        $this->uuidProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturn('REQUEST-ID');

        $this->payPalConfigurationProvider
            ->expects(self::once())
            ->method('getPartnerAttributionId')
            ->with($channel)
            ->willReturn('TRACKING-ID');

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('POST', 'https://test-api.paypal.com/v2/post-request/')
            ->willReturn($request);

        $this->streamFactory
            ->method('createStream')
            ->willReturn($stream);

        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response
            ->expects(self::once())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::once())
            ->method('getContents')
            ->willReturn('{"status": "OK", "id": "123123"}');

        $result = $this->payPalClient->post('v2/post-request/', 'TOKEN', ['parameter' => 'value', 'another_parameter' => 'another_value']);

        self::assertEquals(['status' => 'OK', 'id' => '123123'], $result);
    }

    #[Test]
    public function it_calls_patch_request_on_paypal_api(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $this->channelContext
            ->method('getChannel')
            ->willReturn($channel);

        $this->payPalConfigurationProvider
            ->expects(self::once())
            ->method('getPartnerAttributionId')
            ->with($channel)
            ->willReturn('TRACKING-ID');

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('PATCH', 'https://test-api.paypal.com/v2/patch-request/123123')
            ->willReturn($request);

        $this->streamFactory
            ->method('createStream')
            ->willReturn($stream);

        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response
            ->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response
            ->expects(self::once())
            ->method('getBody')
            ->willReturn($body);

        $body
            ->expects(self::once())
            ->method('getContents')
            ->willReturn('{"status": "OK", "id": "123123"}');

        $result = $this->payPalClient->patch('v2/patch-request/123123', 'TOKEN', ['parameter' => 'value', 'another_parameter' => 'another_value']);

        self::assertEquals(['status' => 'OK', 'id' => '123123'], $result);
    }

    #[Test]
    public function it_throws_exception_if_the_timeout_has_been_reached_the_specified_amount_of_time(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $this->channelContext
            ->method('getChannel')
            ->willReturn($channel);

        $this->payPalConfigurationProvider
            ->expects(self::once())
            ->method('getPartnerAttributionId')
            ->with($channel)
            ->willReturn('TRACKING-ID');

        $this->requestFactory
            ->method('createRequest')
            ->with('GET', 'https://test-api.paypal.com/v2/get-request/')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);

        $this->client
            ->method('sendRequest')
            ->with($request)
            ->willThrowException(new ConnectException('Connection timeout', $request));

        $this->expectException(PayPalApiTimeoutException::class);

        $this->payPalClient->get('v2/get-request/', 'TOKEN');
    }
}
