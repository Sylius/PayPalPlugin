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

use Nyholm\Psr7\Factory\Psr17Factory;
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
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\PayPalPlugin\Client\PayPalClient;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;
use Symfony\Component\Clock\MockClock;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalNetworkFailure;

final class PayPalClientTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private LoggerInterface&MockObject $logger;

    private UuidProviderInterface&MockObject $uuidProvider;

    private PayPalConfigurationProviderInterface&MockObject $payPalConfigurationProvider;

    private ChannelContextInterface&MockObject $channelContext;

    private RequestFactoryInterface&MockObject $requestFactory;

    private StreamFactoryInterface&MockObject $streamFactory;

    private HostProviderInterface&MockObject $hostProvider;

    private MockClock $clock;

    private PayPalClient $payPalClient;

    private int $sentRequestsCount = 0;

    /** @var list<string> */
    private array $sentRequestIds = [];

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
        $this->hostProvider = $this->createMock(HostProviderInterface::class);
        $this->hostProvider->method('getApiBaseUrl')->willReturn('https://test-api.paypal.com/');
        $this->hostProvider->method('getApiBaseUrlForMode')->willReturnMap([
            [true, 'https://test-api.paypal.com/'],
            [false, 'https://api.paypal.com/'],
        ]);
        $this->clock = new MockClock();

        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);

        $this->payPalClient = new PayPalClient(
            $this->client,
            $this->logger,
            $this->uuidProvider,
            $this->payPalConfigurationProvider,
            $this->channelContext,
            $this->hostProvider,
            3,
            $this->requestFactory,
            $this->streamFactory,
            $this->clock,
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

        $result = $this->payPalClient->authorize('CLIENT_ID', 'CLIENT_SECRET', true);

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
            ->method('getStatusCode')
            ->willReturn(401);

        $this->expectException(PayPalAuthorizationException::class);

        $this->payPalClient->authorize('CLIENT_ID', 'CLIENT_SECRET', true);
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
            ->willThrowException(new PayPalNetworkFailure('Connection timeout', $request));

        $this->expectException(PayPalApiTimeoutException::class);

        $this->payPalClient->get('v2/get-request/', 'TOKEN');
    }

    public function test_it_attributes_the_request_to_the_partner(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->payPalConfigurationProvider->method('getPartnerAttributionId')->with($channel)->willReturn('BN_CODE');

        self::assertSame('BN_CODE', $this->headersOfNextGetRequest()['PayPal-Partner-Attribution-Id'] ?? null);
    }

    public function test_it_keeps_talking_to_paypal_when_no_channel_is_in_context(): void
    {
        $this->channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());
        $this->logger->expects(self::once())->method('warning');

        $headers = $this->headersOfNextGetRequest();

        self::assertArrayNotHasKey('PayPal-Partner-Attribution-Id', $headers);
        self::assertSame('Bearer TOKEN', $headers['Authorization']);
    }

    public function test_it_keeps_talking_to_paypal_when_the_channel_has_no_paypal_payment_method(): void
    {
        $this->channelContext->method('getChannel')->willReturn($this->createMock(ChannelInterface::class));
        $this->payPalConfigurationProvider
            ->method('getPartnerAttributionId')
            ->willThrowException(new \InvalidArgumentException('No PayPal payment method defined'))
        ;
        $this->logger->expects(self::once())->method('warning');

        self::assertArrayNotHasKey('PayPal-Partner-Attribution-Id', $this->headersOfNextGetRequest());
    }

    public function test_it_identifies_a_post_request_with_a_fresh_request_id(): void
    {
        $this->uuidProvider->method('provide')->willReturn('FRESH_REQUEST_ID');

        $headers = $this->headersOfNextRequest(fn () => $this->payPalClient->post('v2/checkout/orders', 'TOKEN', []));

        self::assertSame('FRESH_REQUEST_ID', $headers['PayPal-Request-Id']);
    }

    public function test_it_identifies_a_post_request_with_the_request_id_given_by_the_caller(): void
    {
        $this->uuidProvider->method('provide')->willReturn('FRESH_REQUEST_ID');

        $headers = $this->headersOfNextRequest(
            fn () => $this->payPalClient->post('v2/checkout/orders', 'TOKEN', [], ['PayPal-Request-Id' => 'CALLER_REQUEST_ID']),
        );

        self::assertSame('CALLER_REQUEST_ID', $headers['PayPal-Request-Id']);
    }

    public function test_it_retries_a_request_paypal_could_not_handle(): void
    {
        $responses = $this->sendWithResponses([503, 200], fn (PayPalClient $client) => $client->get('v2/checkout/orders/123123', 'TOKEN'));

        self::assertSame([], $responses);
        self::assertSame(2, $this->sentRequestsCount);
    }

    public function test_it_retries_a_rate_limited_request_after_the_delay_paypal_asks_for(): void
    {
        $now = $this->clock->now();

        $this->sendWithResponses([[429, ['Retry-After' => '2']], 200], fn (PayPalClient $client) => $client->get('v2/checkout/orders/123123', 'TOKEN'));

        self::assertSame(2, $this->sentRequestsCount);
        self::assertEquals($now->modify('+2 seconds'), $this->clock->now());
    }

    public function test_it_does_not_wait_longer_than_it_allows_for_a_rate_limited_request(): void
    {
        $this->sendWithResponses([[429, ['Retry-After' => '60']]], fn (PayPalClient $client) => $client->get('v2/checkout/orders/123123', 'TOKEN'));

        self::assertSame(1, $this->sentRequestsCount);
    }

    public function test_it_does_not_retry_a_request_paypal_rejected(): void
    {
        $this->sendWithResponses([400], fn (PayPalClient $client) => $client->get('v2/checkout/orders/123123', 'TOKEN'));

        self::assertSame(1, $this->sentRequestsCount);
    }

    public function test_it_gives_up_after_the_last_attempt(): void
    {
        $this->sendWithResponses([503, 503, 503], fn (PayPalClient $client) => $client->get('v2/checkout/orders/123123', 'TOKEN'));

        self::assertSame(3, $this->sentRequestsCount);
    }

    public function test_it_gives_every_request_its_own_attempts(): void
    {
        $this->sendWithResponses(
            ['network', 'network', 200, 'network', 'network', 200],
            function (PayPalClient $client): void {
                $client->get('v2/checkout/orders/123123', 'TOKEN');
                $client->get('v2/checkout/orders/123123', 'TOKEN');
            },
        );

        self::assertSame(6, $this->sentRequestsCount);
    }

    public function test_it_retries_a_write_with_the_same_request_id(): void
    {
        $this->uuidProvider->method('provide')->willReturnOnConsecutiveCalls('FIRST_REQUEST_ID', 'SECOND_REQUEST_ID');

        $this->sendWithResponses(['network', 503, 201], fn (PayPalClient $client) => $client->post('v2/checkout/orders/123123/capture', 'TOKEN', []));

        self::assertSame(['FIRST_REQUEST_ID', 'FIRST_REQUEST_ID', 'FIRST_REQUEST_ID'], $this->sentRequestIds);
    }

    /** @param list<int|string|array{int, array<string, string>}> $responses */
    private function sendWithResponses(array $responses, callable $send): mixed
    {
        $factory = new Psr17Factory();

        $this->requestFactory->method('createRequest')->willReturnCallback($factory->createRequest(...));
        $this->streamFactory->method('createStream')->willReturnCallback($factory->createStream(...));
        $this->client->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use (&$responses, $factory): ResponseInterface {
                ++$this->sentRequestsCount;
                $this->sentRequestIds[] = $request->getHeaderLine('PayPal-Request-Id');

                $response = array_shift($responses);
                if ('network' === $response) {
                    throw new PayPalNetworkFailure('Connection timeout', $request);
                }

                [$statusCode, $headers] = is_array($response) ? $response : [$response, []];
                $psrResponse = $factory->createResponse($statusCode)->withBody($factory->createStream('[]'));
                foreach ($headers as $name => $value) {
                    $psrResponse = $psrResponse->withHeader($name, $value);
                }

                return $psrResponse;
            },
        );

        return $send($this->payPalClient);
    }

    /** @return array<string, string> */
    private function headersOfNextGetRequest(): array
    {
        return $this->headersOfNextRequest(fn () => $this->payPalClient->get('v2/checkout/orders/123123', 'TOKEN'));
    }

    /** @return array<string, string> */
    private function headersOfNextRequest(callable $sendRequest): array
    {
        $headers = [];

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnCallback(
            function (string $header, string $value) use (&$headers, $request): RequestInterface {
                $headers[$header] = $value;

                return $request;
            },
        );
        $request->method('withBody')->willReturn($request);

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->client->method('sendRequest')->willReturn($response);

        $sendRequest();

        return $headers;
    }
}
