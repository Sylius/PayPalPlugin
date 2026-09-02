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

        $this->executor = new PayPalOnboardingRequestExecutor($this->client, $this->logger);
    }

    #[Test]
    public function it_returns_the_decoded_body_on_success(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->client->method('sendRequest')->with($request)->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"foo": "bar"}');

        self::assertSame(['foo' => 'bar'], $this->executor->execute($request, 'Test'));
    }

    #[Test]
    public function it_throws_a_plugin_exception_on_a_non_successful_status(): void
    {
        $request = $this->createMock(RequestInterface::class);
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
    public function it_redacts_credentials_from_the_error_log_on_a_non_successful_status(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->client->method('sendRequest')->willReturn($response);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"client_secret": "SUPER-SECRET", "access_token": "TOKEN", "error": "invalid"}');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(self::callback(static function (string $message): bool {
                return !str_contains($message, 'SUPER-SECRET') &&
                    !str_contains($message, 'TOKEN') &&
                    str_contains($message, '[redacted]') &&
                    str_contains($message, 'invalid');
            }));

        $this->expectException(PayPalPluginException::class);

        $this->executor->execute($request, 'Seller credentials');
    }

    #[Test]
    public function it_rethrows_transport_exceptions(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $exception = $this->createMock(ClientExceptionInterface::class);

        $this->client->method('sendRequest')->willThrowException($exception);

        $this->expectException(ClientExceptionInterface::class);

        $this->executor->execute($request, 'Test');
    }

    #[Test]
    public function it_throws_a_json_exception_on_malformed_body(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->client->method('sendRequest')->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('<html>not json</html>');

        $this->expectException(\JsonException::class);

        $this->executor->execute($request, 'Test');
    }
}
