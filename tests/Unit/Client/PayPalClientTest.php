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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClient;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiAuthenticationException;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalNetworkFailure;

final class PayPalClientTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private LoggerInterface&MockObject $logger;

    private UuidProviderInterface&MockObject $uuidProvider;

    private RequestFactoryInterface&MockObject $requestFactory;

    private StreamFactoryInterface&MockObject $streamFactory;

    private HostProviderInterface&MockObject $hostProvider;

    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private PaymentMethodInterface&MockObject $paymentMethod;

    /** @var list<string> */
    private array $tokens = [];

    /** @var list<array{authorization: string, requestId: string, body: string}> */
    private array $sentRequests = [];

    private PayPalClient $payPalClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->uuidProvider = $this->createMock(UuidProviderInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->hostProvider = $this->createMock(HostProviderInterface::class);
        $this->hostProvider->method('getApiBaseUrlForMode')->willReturnMap([[true, 'https://test-api.paypal.com/'], [false, 'https://live-api.paypal.com/']]);
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->authorizeClientApi->method('authorize')->willReturnCallback(fn (): string => array_shift($this->tokens) ?? 'TOKEN');
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->paymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, ['getConfig' => ['sandbox' => true, 'partner_attribution_id' => 'TRACKING-ID']]));

        $this->payPalClient = new PayPalClient(
            $this->client,
            $this->logger,
            $this->uuidProvider,
            $this->authorizeClientApi,
            $this->hostProvider,
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
    public function it_calls_get_request_on_paypal_api(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

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
            ->method('__toString')
            ->willReturn('{"status": "OK", "id": "123123"}');

        $result = $this->payPalClient->get($this->paymentMethod, 'v2/get-request/');

        self::assertEquals(['status' => 'OK', 'id' => '123123'], $result);
    }

    #[Test]
    public function it_calls_post_request_on_paypal_api(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $this->uuidProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturn('REQUEST-ID');

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
            ->method('__toString')
            ->willReturn('{"status": "OK", "id": "123123"}');

        $result = $this->payPalClient->post($this->paymentMethod, 'v2/post-request/', ['parameter' => 'value', 'another_parameter' => 'another_value']);

        self::assertEquals(['status' => 'OK', 'id' => '123123'], $result);
    }

    #[Test]
    public function it_calls_patch_request_on_paypal_api(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $stream = $this->createMock(StreamInterface::class);

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
            ->method('__toString')
            ->willReturn('{"status": "OK", "id": "123123"}');

        $result = $this->payPalClient->patch($this->paymentMethod, 'v2/patch-request/123123', ['parameter' => 'value', 'another_parameter' => 'another_value']);

        self::assertEquals(['status' => 'OK', 'id' => '123123'], $result);
    }

    #[Test]
    public function it_throws_exception_if_the_request_did_not_reach_paypal(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory
            ->method('createRequest')
            ->with('GET', 'https://test-api.paypal.com/v2/get-request/')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);

        $this->client
            ->method('sendRequest')
            ->with($request)
            ->willThrowException(new PayPalNetworkFailure('Connection timeout', $request));

        $this->expectException(PayPalApiConnectionException::class);

        $this->payPalClient->get($this->paymentMethod, 'v2/get-request/');
    }

    public function test_it_sends_the_request_to_the_api_of_the_payment_method_mode(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, ['getConfig' => ['sandbox' => false]]));

        $this->requestFactory->expects(self::once())->method('createRequest')->with('GET', 'https://live-api.paypal.com/v2/checkout/orders/123123')->willReturnCallback((new Psr17Factory())->createRequest(...));
        $this->client->method('sendRequest')->willReturn((new Psr17Factory())->createResponse(200));

        $this->payPalClient->get($paymentMethod, 'v2/checkout/orders/123123');
    }

    public function test_it_attributes_the_request_to_the_partner_attribution_id_of_the_payment_method(): void
    {
        self::assertSame('TRACKING-ID', $this->headersOfNextGetRequest()['PayPal-Partner-Attribution-Id'] ?? null);
    }

    public function test_it_keeps_talking_to_paypal_when_the_payment_method_has_no_partner_attribution_id(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, ['getConfig' => ['sandbox' => true]]));
        $this->logger->expects(self::once())->method('warning');
        $this->paymentMethod = $paymentMethod;

        $headers = $this->headersOfNextGetRequest();

        self::assertArrayNotHasKey('PayPal-Partner-Attribution-Id', $headers);
        self::assertSame('Bearer TOKEN', $headers['Authorization']);
    }

    public function test_it_identifies_a_post_request_with_a_fresh_request_id(): void
    {
        $this->uuidProvider->method('provide')->willReturn('FRESH_REQUEST_ID');

        $headers = $this->headersOfNextRequest(fn () => $this->payPalClient->post($this->paymentMethod, 'v2/checkout/orders', []));

        self::assertSame('FRESH_REQUEST_ID', $headers['PayPal-Request-Id']);
    }

    public function test_it_identifies_a_post_request_with_the_request_id_given_by_the_caller(): void
    {
        $this->uuidProvider->method('provide')->willReturn('FRESH_REQUEST_ID');

        $headers = $this->headersOfNextRequest(
            fn () => $this->payPalClient->post($this->paymentMethod, 'v2/checkout/orders', [], ['PayPal-Request-Id' => 'CALLER_REQUEST_ID']),
        );

        self::assertSame('CALLER_REQUEST_ID', $headers['PayPal-Request-Id']);
    }

    public function test_it_retries_once_with_a_fresh_token_when_paypal_rejects_the_cached_one(): void
    {
        $this->tokens = ['REVOKED_TOKEN', 'FRESH_TOKEN'];
        $this->authorizeClientApi->expects(self::once())->method('invalidate')->with($this->paymentMethod);

        $result = $this->sendWithResponses([[401, '{"error": "invalid_token"}'], [200, '{"id": "123123"}']], fn () => $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/123123'));

        self::assertSame(['id' => '123123'], $result);
        self::assertSame(['Bearer REVOKED_TOKEN', 'Bearer FRESH_TOKEN'], array_column($this->sentRequests, 'authorization'));
    }

    public function test_it_gives_up_when_paypal_rejects_the_fresh_token_too(): void
    {
        $this->authorizeClientApi->expects(self::once())->method('invalidate');

        try {
            $this->sendWithResponses([[401, '{"error": "invalid_token"}'], [401, '{"error": "invalid_token"}']], fn () => $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/123123'));
            self::fail('PayPalApiAuthenticationException was not thrown');
        } catch (PayPalApiAuthenticationException $exception) {
            self::assertSame('invalid_token', $exception->getErrorName());
        }

        self::assertCount(2, $this->sentRequests);
    }

    public function test_it_resends_a_write_rejected_for_its_token_with_the_same_request_id_and_body(): void
    {
        $this->uuidProvider->method('provide')->willReturnOnConsecutiveCalls('FIRST_REQUEST_ID', 'SECOND_REQUEST_ID');

        $this->sendWithResponses([[401, '{}'], [201, '{}']], fn () => $this->payPalClient->post($this->paymentMethod, 'v2/checkout/orders/123123/capture', ['note' => 'capture']));

        self::assertSame(['FIRST_REQUEST_ID', 'FIRST_REQUEST_ID'], array_column($this->sentRequests, 'requestId'));
        self::assertSame(['{"note":"capture"}', '{"note":"capture"}'], array_column($this->sentRequests, 'body'));
    }

    public function test_it_keeps_the_token_when_paypal_rejects_the_request_for_another_reason(): void
    {
        $this->authorizeClientApi->expects(self::never())->method('invalidate');

        try {
            $this->sendWithResponses([[403, '{"name": "NOT_AUTHORIZED"}']], fn () => $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/123123'));
            self::fail('PayPalApiRequestException was not thrown');
        } catch (PayPalApiRequestException) {
        }

        self::assertCount(1, $this->sentRequests);
    }

    public function test_it_throws_the_error_paypal_answers_with(): void
    {
        try {
            $this->sendWithResponses(
                [[422, '{"name": "UNPROCESSABLE_ENTITY", "details": [{"issue": "INSTRUMENT_DECLINED"}], "debug_id": "DEBUG_ID"}']],
                fn () => $this->payPalClient->post($this->paymentMethod, 'v2/checkout/orders/123123/capture'),
            );
            self::fail('PayPalApiRequestException was not thrown');
        } catch (PayPalApiRequestException $exception) {
            self::assertSame('POST', $exception->getMethod());
            self::assertSame('v2/checkout/orders/123123/capture', $exception->getPath());
            self::assertTrue($exception->hasIssue('INSTRUMENT_DECLINED'));
        }
    }

    public function test_it_answers_a_request_paypal_accepted_without_content_with_nothing(): void
    {
        self::assertSame([], $this->sendWithResponses([[204, '']], fn () => $this->payPalClient->patch($this->paymentMethod, 'v2/checkout/orders/123123', [])));
    }

    /** @param list<array{int, string}> $responses */
    private function sendWithResponses(array $responses, callable $send): mixed
    {
        $factory = new Psr17Factory();

        $this->requestFactory->method('createRequest')->willReturnCallback($factory->createRequest(...));
        $this->streamFactory->method('createStream')->willReturnCallback($factory->createStream(...));
        $this->client->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use (&$responses, $factory): ResponseInterface {
                $this->sentRequests[] = [
                    'authorization' => $request->getHeaderLine('Authorization'),
                    'requestId' => $request->getHeaderLine('PayPal-Request-Id'),
                    'body' => (string) $request->getBody(),
                ];
                [$statusCode, $body] = array_shift($responses) ?? throw new \LogicException('No more responses to give.');

                return $factory->createResponse($statusCode)->withBody($factory->createStream($body));
            },
        );

        return $send();
    }

    /** @return array<string, string> */
    private function headersOfNextGetRequest(): array
    {
        return $this->headersOfNextRequest(fn () => $this->payPalClient->get($this->paymentMethod, 'v2/checkout/orders/123123'));
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
        $body->method('__toString')->willReturn('{}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->client->method('sendRequest')->willReturn($response);

        $sendRequest();

        return $headers;
    }
}
