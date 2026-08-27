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
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApi;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;

final class MerchantOnboardingStatusApiTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private RequestFactoryInterface&MockObject $requestFactory;

    private LoggerInterface&MockObject $logger;

    private MerchantOnboardingStatusApi $merchantOnboardingStatusApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->merchantOnboardingStatusApi = new MerchantOnboardingStatusApi(
            $this->client,
            'http://base-url.com/',
            $this->requestFactory,
            $this->logger,
        );
    }

    #[Test]
    public function it_returns_a_complete_onboarding_status(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'http://base-url.com/v1/customer/partners/PARTNER-ID/merchant-integrations/MERCHANT-ID')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);
        $this->client->method('sendRequest')->with($request)->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn(
            '{"payments_receivable": true, "primary_email_confirmed": true}',
        );

        $status = $this->merchantOnboardingStatusApi->get('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID');

        self::assertTrue($status->arePaymentsReceivable());
        self::assertTrue($status->isPrimaryEmailConfirmed());
        self::assertTrue($status->isComplete());
    }

    #[Test]
    public function it_returns_an_incomplete_status_when_flags_are_missing(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $this->client->method('sendRequest')->willReturn($response);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"payments_receivable": true}');

        $status = $this->merchantOnboardingStatusApi->get('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID');

        self::assertTrue($status->arePaymentsReceivable());
        self::assertFalse($status->isPrimaryEmailConfirmed());
        self::assertFalse($status->isComplete());
    }

    #[Test]
    public function it_throws_an_exception_when_the_response_is_not_successful(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $this->client->method('sendRequest')->willReturn($response);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"error": "invalid_token"}');

        $this->expectException(PayPalPluginException::class);

        $this->merchantOnboardingStatusApi->get('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID');
    }
}
