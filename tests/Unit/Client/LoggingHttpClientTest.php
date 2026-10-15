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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sylius\PayPalPlugin\Client\LoggingHttpClient;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalNetworkFailure;

final class LoggingHttpClientTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private LoggerInterface&MockObject $logger;

    private Psr17Factory $factory;

    private LoggingHttpClient $loggingHttpClient;

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->factory = new Psr17Factory();

        $this->logger->method('log')->willReturnCallback(function (string $level, string $message, array $context): void {
            $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
        });
        $this->logger->method('error')->willReturnCallback(function (string $message, array $context): void {
            $this->logs[] = ['level' => LogLevel::ERROR, 'message' => $message, 'context' => $context];
        });

        $this->loggingHttpClient = new LoggingHttpClient($this->client, $this->logger);
    }

    public function test_it_logs_a_successful_call_with_its_request_response_and_debug_id(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(201, '{"id": "ORDER_ID", "status": "CREATED"}', ['PayPal-Debug-Id' => 'DEBUG_ID']));

        $this->loggingHttpClient->sendRequest($this->request('POST', '{"intent": "CAPTURE"}', ['PayPal-Request-Id' => 'REQUEST_ID']));

        self::assertSame(LogLevel::INFO, $this->logs[0]['level']);
        self::assertSame('PayPal API request POST https://api.sandbox.paypal.com/v2/checkout/orders answered with HTTP 201 (PayPal-Debug-Id: DEBUG_ID)', $this->logs[0]['message']);
        self::assertSame('REQUEST_ID', $this->logs[0]['context']['request']['headers']['PayPal-Request-Id']);
        self::assertSame(['intent' => 'CAPTURE'], $this->logs[0]['context']['request']['body']);
        self::assertSame('DEBUG_ID', $this->logs[0]['context']['response']['headers']['PayPal-Debug-Id']);
        self::assertSame(['id' => 'ORDER_ID', 'status' => 'CREATED'], $this->logs[0]['context']['response']['body']);
    }

    public function test_it_logs_a_call_paypal_rejected_as_an_error(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(422, '{"name": "UNPROCESSABLE_ENTITY", "details": [{"issue": "INSTRUMENT_DECLINED"}]}'));

        $this->loggingHttpClient->sendRequest($this->request('POST', '{}'));

        self::assertSame(LogLevel::ERROR, $this->logs[0]['level']);
        self::assertSame(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'INSTRUMENT_DECLINED']]], $this->logs[0]['context']['response']['body']);
    }

    public function test_it_logs_a_call_that_never_reached_paypal_as_an_error_and_rethrows_the_failure(): void
    {
        $request = $this->request('GET');
        $failure = new PayPalNetworkFailure('Connection timed out', $request);
        $this->client->method('sendRequest')->willThrowException($failure);

        try {
            $this->loggingHttpClient->sendRequest($request);
            self::fail('The failure was not rethrown');
        } catch (PayPalNetworkFailure $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame(LogLevel::ERROR, $this->logs[0]['level']);
        self::assertSame('PayPal API request GET https://api.sandbox.paypal.com/v2/checkout/orders failed: Connection timed out', $this->logs[0]['message']);
    }

    public function test_it_masks_the_credentials_in_the_headers(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(200, '{}'));

        $this->loggingHttpClient->sendRequest($this->request('POST', '{}', ['Authorization' => 'Bearer ACCESS_TOKEN', 'PayPal-Auth-Assertion' => 'ASSERTION']));

        self::assertSame('***', $this->logs[0]['context']['request']['headers']['Authorization']);
        self::assertSame('***', $this->logs[0]['context']['request']['headers']['PayPal-Auth-Assertion']);
    }

    public function test_it_masks_the_credentials_paypal_hands_out(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(200, '{"access_token": "ACCESS_TOKEN", "expires_in": 32400, "client_id": "CLIENT_ID", "client_secret": "SECRET", "payer_id": "PAYER_ID"}'));

        $this->loggingHttpClient->sendRequest($this->request('POST', 'grant_type=authorization_code&code=AUTH_CODE&code_verifier=NONCE', ['Content-Type' => 'application/x-www-form-urlencoded']));

        self::assertSame(['grant_type' => 'authorization_code', 'code' => '***', 'code_verifier' => '***'], $this->logs[0]['context']['request']['body']);
        self::assertSame(['access_token' => '***', 'expires_in' => 32400, 'client_id' => '***', 'client_secret' => '***', 'payer_id' => '***'], $this->logs[0]['context']['response']['body']);
    }

    public function test_it_masks_the_personal_data_of_the_payer(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(200, (string) json_encode([
            'id' => 'ORDER_ID',
            'payer' => ['name' => ['given_name' => 'Oliver', 'surname' => 'Queen'], 'email_address' => 'oliver@example.com', 'phone' => ['phone_number' => ['national_number' => '123456789']]],
            'payment_source' => ['card' => ['name' => 'Oliver Queen', 'number' => '4111111111111111', 'security_code' => '123', 'expiry' => '2030-12', 'last_digits' => '1111']],
            'purchase_units' => [['items' => [['name' => 'T-Shirt']], 'shipping' => ['name' => ['full_name' => 'Oliver Queen'], 'address' => ['address_line_1' => 'Star City 1']]]],
        ])));

        $this->loggingHttpClient->sendRequest($this->request('GET'));

        self::assertSame([
            'id' => 'ORDER_ID',
            'payer' => ['name' => ['given_name' => '***', 'surname' => '***'], 'email_address' => '***', 'phone' => '***'],
            'payment_source' => ['card' => ['name' => '***', 'number' => '***', 'security_code' => '***', 'expiry' => '***', 'last_digits' => '1111']],
            'purchase_units' => [['items' => [['name' => 'T-Shirt']], 'shipping' => ['name' => ['full_name' => '***'], 'address' => '***']]],
        ], $this->logs[0]['context']['response']['body']);
    }

    public function test_it_leaves_the_response_body_readable_for_the_caller(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(200, '{"id": "ORDER_ID"}'));

        self::assertSame('{"id": "ORDER_ID"}', $this->loggingHttpClient->sendRequest($this->request('GET'))->getBody()->getContents());
    }

    /** @param array<string, string> $headers */
    private function request(string $method, string $body = '', array $headers = []): RequestInterface
    {
        $request = $this->factory->createRequest($method, 'https://api.sandbox.paypal.com/v2/checkout/orders')->withBody($this->factory->createStream($body));
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /** @param array<string, string> $headers */
    private function response(int $statusCode, string $body, array $headers = []): ResponseInterface
    {
        $response = $this->factory->createResponse($statusCode)->withBody($this->factory->createStream($body));
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
