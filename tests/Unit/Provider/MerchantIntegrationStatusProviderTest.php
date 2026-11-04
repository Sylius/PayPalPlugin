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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Model\AccessToken;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PartnerCredentials;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProvider;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;

final class MerchantIntegrationStatusProviderTest extends TestCase
{
    private AuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private MerchantOnboardingStatusApiInterface&MockObject $merchantOnboardingStatusApi;

    private PartnerCredentialsProviderInterface&MockObject $partnerCredentialsProvider;

    private MerchantIntegrationStatusProvider $provider;

    protected function setUp(): void
    {
        $this->authorizeClientApi = $this->createMock(AuthorizeClientApiInterface::class);
        $this->merchantOnboardingStatusApi = $this->createMock(MerchantOnboardingStatusApiInterface::class);
        $this->partnerCredentialsProvider = $this->createMock(PartnerCredentialsProviderInterface::class);

        $this->provider = new MerchantIntegrationStatusProvider(
            $this->authorizeClientApi,
            $this->merchantOnboardingStatusApi,
            $this->partnerCredentialsProvider,
            new NullLogger(),
        );
    }

    public function test_it_implements_the_merchant_integration_status_provider_interface(): void
    {
        self::assertInstanceOf(MerchantIntegrationStatusProviderInterface::class, $this->provider);
    }

    public function test_it_reads_the_status_with_the_seller_token_under_the_configured_partner(): void
    {
        $status = new OnboardingStatus(true, true, ['APPLE_PAY']);
        $this->authorizeClientApi->expects(self::once())->method('authorize')->with('CLIENT-ID', 'SECRET', true)->willReturn(new AccessToken('SELLER-TOKEN', 3600));
        $this->partnerCredentialsProvider->method('provide')->with(true)->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));
        $this->merchantOnboardingStatusApi->expects(self::once())->method('get')->with('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID', true)->willReturn($status);

        self::assertSame($status, $this->provider->provide($this->config()));
    }

    public function test_it_asks_under_the_facilitator_partner_first_and_falls_back_to_the_configured_one(): void
    {
        $status = new OnboardingStatus(true, true, ['APPLE_PAY']);
        $this->authorizeClientApi->method('authorize')->willReturn(new AccessToken('SELLER-TOKEN', 3600));
        $this->partnerCredentialsProvider->method('provide')->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));
        $this->merchantOnboardingStatusApi
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(static fn (string $token, string $partnerId): OnboardingStatus => 'PARTNER-ID' === $partnerId
                ? $status
                : throw new PayPalPluginException('Onboarding status request failed with HTTP 404 (PayPal-Debug-Id: x)'))
        ;

        self::assertSame($status, $this->provider->provide($this->config(['sylius_merchant_id' => 'FACILITATOR-PARTNER-ID'])));
    }

    public function test_it_reads_the_status_of_a_facilitator_onboarded_merchant_under_the_facilitator_partner(): void
    {
        $status = new OnboardingStatus(true, true);
        $this->authorizeClientApi->method('authorize')->willReturn(new AccessToken('SELLER-TOKEN', 3600));
        $this->partnerCredentialsProvider->method('provide')->willThrowException(new PayPalPluginException('partner_id/partner_client_id is not configured'));
        $this->merchantOnboardingStatusApi->expects(self::once())->method('get')->with('SELLER-TOKEN', 'FACILITATOR-PARTNER-ID', 'MERCHANT-ID', true)->willReturn($status);

        self::assertSame($status, $this->provider->provide($this->config(['sylius_merchant_id' => 'FACILITATOR-PARTNER-ID'])));
    }

    public function test_it_returns_no_status_when_paypal_refuses_the_seller_credentials(): void
    {
        $this->authorizeClientApi->method('authorize')->willThrowException(new PayPalPluginException('invalid_client'));
        $this->merchantOnboardingStatusApi->expects(self::never())->method('get');

        self::assertNull($this->provider->provide($this->config()));
    }

    public function test_it_returns_no_status_when_no_partner_knows_the_merchant(): void
    {
        $this->authorizeClientApi->method('authorize')->willReturn(new AccessToken('SELLER-TOKEN', 3600));
        $this->partnerCredentialsProvider->method('provide')->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));
        $this->merchantOnboardingStatusApi->method('get')->willThrowException(new PayPalPluginException('Onboarding status request failed with HTTP 404 (PayPal-Debug-Id: x)'));

        self::assertNull($this->provider->provide($this->config()));
    }

    public function test_it_returns_no_status_for_a_payment_method_without_credentials(): void
    {
        $this->authorizeClientApi->expects(self::never())->method('authorize');

        self::assertNull($this->provider->provide(PayPalGatewayConfig::fromArray([])));
    }

    /** @param array<string, mixed> $extra */
    private function config(array $extra = []): PayPalGatewayConfig
    {
        return PayPalGatewayConfig::fromArray(array_merge(['client_id' => 'CLIENT-ID', 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT-ID', 'sandbox' => true], $extra));
    }
}
