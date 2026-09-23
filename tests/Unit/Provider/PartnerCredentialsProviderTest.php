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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Sylius\PayPalPlugin\Api\PayPalOnboardingRequestExecutorInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProvider;

final class PartnerCredentialsProviderTest extends TestCase
{
    private PayPalOnboardingRequestExecutorInterface&MockObject $requestExecutor;

    private RequestFactoryInterface&MockObject $requestFactory;

    private CacheItemPoolInterface&MockObject $cache;

    private PartnerCredentialsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestExecutor = $this->createMock(PayPalOnboardingRequestExecutorInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);

        $this->provider = new PartnerCredentialsProvider(
            $this->requestExecutor,
            $this->requestFactory,
            $this->cache,
            'https://partner.example.com/partner-credentials',
        );
    }

    #[Test]
    public function it_fetches_and_caches_partner_credentials_on_a_cache_miss(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $item = $this->createMock(CacheItemInterface::class);

        $this->cache->method('getItem')->with('sylius_paypal.partner_credentials')->willReturn($item);
        $item->method('isHit')->willReturn(false);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'https://partner.example.com/partner-credentials')
            ->willReturn($request);
        $request->method('withHeader')->willReturn($request);

        $this->requestExecutor
            ->expects(self::once())
            ->method('execute')
            ->with($request, 'Partner credentials')
            ->willReturn([
                'partner_id' => 'PARTNER-ID',
                'partner_client_id' => 'PARTNER-CLIENT-ID',
                'partner_logo_url' => 'https://shop.example.com/logo.png',
            ]);

        $item->expects(self::once())->method('set')->with([
            'partner_id' => 'PARTNER-ID',
            'partner_client_id' => 'PARTNER-CLIENT-ID',
            'partner_logo_url' => 'https://shop.example.com/logo.png',
        ])->willReturn($item);
        $item->expects(self::once())->method('expiresAfter')->willReturn($item);
        $this->cache->expects(self::once())->method('save')->with($item);

        $credentials = $this->provider->provide();

        self::assertSame('PARTNER-ID', $credentials->getPartnerId());
        self::assertSame('PARTNER-CLIENT-ID', $credentials->getPartnerClientId());
        self::assertSame('https://shop.example.com/logo.png', $credentials->getPartnerLogoUrl());
    }

    #[Test]
    public function it_defaults_the_partner_logo_url_to_an_empty_string_when_absent(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $item = $this->createMock(CacheItemInterface::class);

        $this->cache->method('getItem')->willReturn($item);
        $item->method('isHit')->willReturn(false);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);

        $this->requestExecutor
            ->method('execute')
            ->willReturn(['partner_id' => 'PARTNER-ID', 'partner_client_id' => 'PARTNER-CLIENT-ID']);

        $item->expects(self::once())->method('set')->with([
            'partner_id' => 'PARTNER-ID',
            'partner_client_id' => 'PARTNER-CLIENT-ID',
            'partner_logo_url' => '',
        ])->willReturn($item);
        $item->method('expiresAfter')->willReturn($item);

        $credentials = $this->provider->provide();

        self::assertSame('', $credentials->getPartnerLogoUrl());
    }

    #[Test]
    public function it_returns_cached_credentials_without_calling_the_endpoint(): void
    {
        $item = $this->createMock(CacheItemInterface::class);

        $this->cache->method('getItem')->willReturn($item);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(['partner_id' => 'CACHED-ID', 'partner_client_id' => 'CACHED-CLIENT-ID']);

        $this->requestExecutor->expects(self::never())->method('execute');

        $credentials = $this->provider->provide();

        self::assertSame('CACHED-ID', $credentials->getPartnerId());
        self::assertSame('CACHED-CLIENT-ID', $credentials->getPartnerClientId());
    }

    #[Test]
    public function it_throws_an_exception_when_the_response_is_missing_keys(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturn($request);
        $item = $this->createMock(CacheItemInterface::class);
        $this->cache->method('getItem')->willReturn($item);
        $item->method('isHit')->willReturn(false);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->requestExecutor->method('execute')->willReturn(['partner_id' => 'PARTNER-ID']);

        $this->expectException(PayPalPluginException::class);

        $this->provider->provide();
    }

    #[Test]
    public function it_throws_an_exception_when_the_response_contains_empty_credentials(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturn($request);
        $item = $this->createMock(CacheItemInterface::class);
        $this->cache->method('getItem')->willReturn($item);
        $item->method('isHit')->willReturn(false);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->requestExecutor->method('execute')->willReturn(['partner_id' => '', 'partner_client_id' => '']);

        $item->expects(self::never())->method('set');
        $this->cache->expects(self::never())->method('save');

        $this->expectException(PayPalPluginException::class);

        $this->provider->provide();
    }

    #[Test]
    public function it_falls_back_to_the_configured_credentials_when_the_request_fails_and_a_fallback_is_configured(): void
    {
        $provider = new PartnerCredentialsProvider(
            $this->requestExecutor,
            $this->requestFactory,
            $this->cache,
            'https://partner.example.com/partner-credentials',
            3600,
            'FALLBACK-PARTNER-ID',
            'FALLBACK-PARTNER-CLIENT-ID',
        );

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturn($request);
        $item = $this->createMock(CacheItemInterface::class);
        $this->cache->method('getItem')->willReturn($item);
        $item->method('isHit')->willReturn(false);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->requestExecutor->method('execute')->willThrowException(new \RuntimeException('Timed out'));

        $item->expects(self::never())->method('set');
        $this->cache->expects(self::never())->method('save');

        $credentials = $provider->provide();

        self::assertSame('FALLBACK-PARTNER-ID', $credentials->getPartnerId());
        self::assertSame('FALLBACK-PARTNER-CLIENT-ID', $credentials->getPartnerClientId());
    }

    #[Test]
    public function it_rethrows_when_the_request_fails_and_no_fallback_is_configured(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturn($request);
        $item = $this->createMock(CacheItemInterface::class);
        $this->cache->method('getItem')->willReturn($item);
        $item->method('isHit')->willReturn(false);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->requestExecutor->method('execute')->willThrowException(new \RuntimeException('Timed out'));

        $this->expectException(\RuntimeException::class);

        $this->provider->provide();
    }
}
