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
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Sylius\PayPalPlugin\Api\PayPalOnboardingRequestExecutorInterface;
use Sylius\PayPalPlugin\Api\SellerCredentialsApi;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;

final class SellerCredentialsApiTest extends TestCase
{
    private PayPalOnboardingRequestExecutorInterface&MockObject $requestExecutor;

    private RequestFactoryInterface&MockObject $requestFactory;

    private SellerCredentialsApi $sellerCredentialsApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestExecutor = $this->createMock(PayPalOnboardingRequestExecutorInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);

        $this->sellerCredentialsApi = new SellerCredentialsApi(
            $this->requestExecutor,
            'http://base-url.com/',
            $this->requestFactory,
        );
    }

    #[Test]
    public function it_returns_the_seller_credentials(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with('GET', 'http://base-url.com/v1/customer/partners/PARTNER-ID/merchant-integrations/credentials')
            ->willReturn($request);

        $request->method('withHeader')->willReturn($request);

        $this->requestExecutor
            ->expects(self::once())
            ->method('execute')
            ->with($request, 'Seller credentials')
            ->willReturn([
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'payer_id' => 'MERCHANT-ID',
            ]);

        self::assertSame(
            [
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'payer_id' => 'MERCHANT-ID',
            ],
            $this->sellerCredentialsApi->get('ONBOARDING-TOKEN', 'PARTNER-ID'),
        );
    }

    #[Test]
    public function it_throws_an_exception_when_credentials_are_incomplete(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $this->requestExecutor->method('execute')->willReturn(['client_id' => 'CLIENT-ID']);

        $this->expectException(PayPalPluginException::class);

        $this->sellerCredentialsApi->get('ONBOARDING-TOKEN', 'PARTNER-ID');
    }
}
