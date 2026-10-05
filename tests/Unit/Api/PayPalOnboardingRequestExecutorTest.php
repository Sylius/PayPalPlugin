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

use Nyholm\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Api\PayPalOnboardingRequestExecutor;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;

final class PayPalOnboardingRequestExecutorTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private LoggerInterface&MockObject $logger;

    private PayPalOnboardingRequestExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->executor = new PayPalOnboardingRequestExecutor($this->client, $this->logger, 'Sylius_MP_PPCP');
    }

    #[Test]
    public function it_returns_the_decoded_body_on_success(): void
    {
        $request = new Request('GET', 'https://api.sandbox.paypal.com/');
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->client->method('sendRequest')->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"foo": "bar"}');

        self::assertSame(['foo' => 'bar'], $this->executor->execute($request, 'Test'));
    }

    #[Test]
    public function it_throws_a_plugin_exception_on_a_non_successful_status(): void
    {
        $request = new Request('GET', 'https://api.sandbox.paypal.com/');
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->client->method('sendRequest')->willReturn($response);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"error": "invalid_token"}');

        $this->expectException(PayPalPluginException::class);

        $this->executor->execute($request, 'Test');
    }

    #[Test]
    public function it_rethrows_transport_exceptions(): void
    {
        $request = new Request('GET', 'https://api.sandbox.paypal.com/');
        $exception = $this->createMock(ClientExceptionInterface::class);

        $this->client->method('sendRequest')->willThrowException($exception);

        $this->expectException(ClientExceptionInterface::class);

        $this->executor->execute($request, 'Test');
    }

    #[Test]
    public function it_throws_a_json_exception_on_malformed_body(): void
    {
        $request = new Request('GET', 'https://api.sandbox.paypal.com/');
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->client->method('sendRequest')->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('<html>not json</html>');

        $this->expectException(\JsonException::class);

        $this->executor->execute($request, 'Test');
    }

    #[Test]
    public function it_sends_the_partner_attribution_id_with_every_request(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(
                fn (RequestInterface $request): bool => 'Sylius_MP_PPCP' === $request->getHeaderLine('PayPal-Partner-Attribution-Id'),
            ))
            ->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{}');

        $this->executor->execute(new Request('GET', 'https://api.sandbox.paypal.com/'), 'Test');
    }

    #[Test]
    public function it_logs_the_paypal_debug_id_of_a_successful_request(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(200, '{}', 'DEBUG-ID'));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Test request succeeded with HTTP 200 (PayPal-Debug-Id: DEBUG-ID)');

        $this->executor->execute(new Request('GET', 'https://api.sandbox.paypal.com/'), 'Test');
    }

    #[Test]
    public function it_logs_the_paypal_debug_id_of_a_failed_request(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(400, '{"name":"INVALID_REQUEST"}', 'DEBUG-ID'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Test request failed with HTTP 400 (PayPal-Debug-Id: DEBUG-ID): {"name":"INVALID_REQUEST"}');

        $this->expectException(PayPalPluginException::class);
        $this->expectExceptionMessage('Test request failed with HTTP 400 (PayPal-Debug-Id: DEBUG-ID)');

        $this->executor->execute(new Request('GET', 'https://api.sandbox.paypal.com/'), 'Test');
    }

    private function response(int $statusCode, string $body, string $debugId): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaderLine')->with('PayPal-Debug-Id')->willReturn($debugId);

        return $response;
    }
}
