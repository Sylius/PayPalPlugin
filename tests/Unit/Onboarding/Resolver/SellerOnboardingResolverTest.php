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

namespace Tests\Sylius\PayPalPlugin\Unit\Onboarding\Resolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Api\OnboardingTokenApiInterface;
use Sylius\PayPalPlugin\Api\SellerCredentialsApiInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolver;

final class SellerOnboardingResolverTest extends TestCase
{
    private OnboardingTokenApiInterface&MockObject $onboardingTokenApi;

    private SellerCredentialsApiInterface&MockObject $sellerCredentialsApi;

    private AuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private MerchantOnboardingStatusApiInterface&MockObject $merchantOnboardingStatusApi;

    private SellerOnboardingResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardingTokenApi = $this->createMock(OnboardingTokenApiInterface::class);
        $this->sellerCredentialsApi = $this->createMock(SellerCredentialsApiInterface::class);
        $this->authorizeClientApi = $this->createMock(AuthorizeClientApiInterface::class);
        $this->merchantOnboardingStatusApi = $this->createMock(MerchantOnboardingStatusApiInterface::class);

        $this->resolver = new SellerOnboardingResolver(
            $this->onboardingTokenApi,
            $this->sellerCredentialsApi,
            $this->authorizeClientApi,
            $this->merchantOnboardingStatusApi,
            'PARTNER-ID',
        );
    }

    #[Test]
    public function it_resolves_seller_onboarding_result_running_all_steps(): void
    {
        $this->onboardingTokenApi
            ->expects(self::once())
            ->method('getFromAuthorizationCode')
            ->with('SHARED-ID', 'AUTH-CODE', 'SELLER-NONCE')
            ->willReturn('ONBOARDING-TOKEN');

        $this->sellerCredentialsApi
            ->expects(self::once())
            ->method('get')
            ->with('ONBOARDING-TOKEN', 'PARTNER-ID')
            ->willReturn([
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'payer_id' => 'MERCHANT-ID',
            ]);

        $this->authorizeClientApi
            ->expects(self::once())
            ->method('authorize')
            ->with('CLIENT-ID', 'CLIENT-SECRET')
            ->willReturn('SELLER-TOKEN');

        $status = new OnboardingStatus(true, true);
        $this->merchantOnboardingStatusApi
            ->expects(self::once())
            ->method('get')
            ->with('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID')
            ->willReturn($status);

        $result = $this->resolver->resolve('AUTH-CODE', 'SHARED-ID', 'SELLER-NONCE');

        self::assertSame('CLIENT-ID', $result->getClientId());
        self::assertSame('CLIENT-SECRET', $result->getClientSecret());
        self::assertSame('MERCHANT-ID', $result->getMerchantId());
        self::assertSame($status, $result->getStatus());
    }
}
