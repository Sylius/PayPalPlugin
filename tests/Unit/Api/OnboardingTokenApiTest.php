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
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Api\OnboardingTokenApi;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;

final class OnboardingTokenApiTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private RequestFactoryInterface&MockObject $requestFactory;

    private StreamFactoryInterface&MockObject $streamFactory;

    private LoggerInterface&MockObject $logger;

    private OnboardingTokenApi $onboardingTokenApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->onboardingTokenApi = new OnboardingTokenApi(
            $this->client,
            'http://base-url.com/',
            $this->requestFactory,
            $this->streamFactory,
            $this->logger,
        );
    }

    #[Test]
    public function it_returns_the_onboarding_access_token(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('POST', 'http://base-url.com/v1/oauth2/token')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);
        $this->streamFactory->method('createStream')->willReturn($stream);

        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"access_token": "ONBOARDING-TOKEN"}');

        self::assertSame(
            'ONBOARDING-TOKEN',
            $this->onboardingTokenApi->getFromAuthorizationCode('SHARED-ID', 'AUTH-CODE', 'SELLER-NONCE'),
        );
    }

    #[Test]
    public function it_throws_an_exception_when_the_access_token_is_missing(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $request->method('withBody')->willReturn($request);
        $this->streamFactory->method('createStream')->willReturn($stream);
        $this->client->method('sendRequest')->willReturn($response);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"error": "invalid_grant"}');

        $this->expectException(PayPalPluginException::class);

        $this->onboardingTokenApi->getFromAuthorizationCode('SHARED-ID', 'AUTH-CODE', 'SELLER-NONCE');
    }
}
