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

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApi;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalNetworkFailure;

final class AuthorizeClientApiTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private Psr17Factory $factory;

    private AuthorizeClientApi $authorizeClientApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->factory = new Psr17Factory();
        $hostProvider = $this->createStub(HostProviderInterface::class);
        $hostProvider->method('getApiBaseUrlForMode')->willReturnMap([[true, 'https://sandbox-api.paypal.com/'], [false, 'https://live-api.paypal.com/']]);
        $this->authorizeClientApi = new AuthorizeClientApi($this->client, $this->factory, $this->factory, $hostProvider);
    }

    #[Test]
    public function it_implements_authorize_client_api_interface(): void
    {
        self::assertInstanceOf(AuthorizeClientApiInterface::class, $this->authorizeClientApi);
    }

    public function test_it_returns_the_access_token_with_the_lifetime_paypal_gives_it(): void
    {
        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(static fn (RequestInterface $request): bool => 'POST' === $request->getMethod() &&
                'https://sandbox-api.paypal.com/v1/oauth2/token' === (string) $request->getUri() &&
                'Basic ' . base64_encode('CLIENT_ID:CLIENT_SECRET') === $request->getHeaderLine('Authorization') &&
                'grant_type=client_credentials' === (string) $request->getBody()))
            ->willReturn($this->response(200, '{"access_token": "TOKEN", "expires_in": 32400}'))
        ;

        $accessToken = $this->authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET', true);

        self::assertSame('TOKEN', $accessToken->value());
        self::assertSame(32400, $accessToken->expiresIn());
    }

    public function test_it_requests_the_access_token_from_the_api_of_the_given_mode(): void
    {
        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(static fn (RequestInterface $request): bool => 'https://live-api.paypal.com/v1/oauth2/token' === (string) $request->getUri()))
            ->willReturn($this->response(200, '{"access_token": "TOKEN", "expires_in": 32400}'))
        ;

        self::assertSame('TOKEN', $this->authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET', false)->value());
    }

    public function test_it_throws_an_exception_if_paypal_rejects_the_credentials(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(401, '{"error": "invalid_client"}'));

        $this->expectException(PayPalAuthorizationException::class);

        $this->authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET', false);
    }

    public function test_it_throws_an_exception_if_paypal_returns_no_access_token(): void
    {
        $this->client->method('sendRequest')->willReturn($this->response(200, '{}'));

        $this->expectException(PayPalAuthorizationException::class);

        $this->authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET', true);
    }

    public function test_it_throws_an_exception_if_the_request_did_not_reach_paypal(): void
    {
        $this->client->method('sendRequest')->willReturnCallback(static fn (RequestInterface $request) => throw new PayPalNetworkFailure('Connection timeout', $request));

        $this->expectException(PayPalApiTimeoutException::class);

        $this->authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET', true);
    }

    private function response(int $statusCode, string $body): ResponseInterface
    {
        return $this->factory->createResponse($statusCode)->withBody($this->factory->createStream($body));
    }
}
