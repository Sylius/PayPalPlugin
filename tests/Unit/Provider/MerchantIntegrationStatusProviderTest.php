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
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiAuthenticationException;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Model\PartnerCredentials;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProvider;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;

final class MerchantIntegrationStatusProviderTest extends TestCase
{
    private const API_BASE_URL = 'https://api-m.sandbox.paypal.com/';

    private GenericApiInterface&MockObject $genericApi;

    private PartnerCredentialsProviderInterface&MockObject $partnerCredentialsProvider;

    private MerchantIntegrationStatusProvider $provider;

    protected function setUp(): void
    {
        $this->genericApi = $this->createMock(GenericApiInterface::class);
        $this->partnerCredentialsProvider = $this->createMock(PartnerCredentialsProviderInterface::class);

        $hostProvider = $this->createStub(HostProviderInterface::class);
        $hostProvider->method('getApiBaseUrlForMode')->willReturnCallback(static fn (bool $sandbox): string => $sandbox ? self::API_BASE_URL : 'https://api-m.paypal.com/');

        $this->provider = new MerchantIntegrationStatusProvider(
            $this->genericApi,
            $hostProvider,
            $this->partnerCredentialsProvider,
            new NullLogger(),
        );
    }

    public function test_it_implements_the_merchant_integration_status_provider_interface(): void
    {
        self::assertInstanceOf(MerchantIntegrationStatusProviderInterface::class, $this->provider);
    }

    public function test_it_reads_the_status_through_the_seller_client_under_the_configured_partner(): void
    {
        $paymentMethod = $this->paymentMethod();
        $this->partnerCredentialsProvider->method('provide')->with(true)->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));
        $this->genericApi
            ->expects(self::once())
            ->method('get')
            ->with($paymentMethod, self::API_BASE_URL . 'v1/customer/partners/PARTNER-ID/merchant-integrations/MERCHANT-ID')
            ->willReturn($this->merchantIntegration())
        ;

        $status = $this->provider->provide($paymentMethod);

        self::assertNotNull($status);
        self::assertTrue($status->isComplete());
        self::assertSame(['APPLE_PAY'], $status->activeCapabilities());
    }

    public function test_it_returns_no_status_when_the_partner_is_not_configured(): void
    {
        $this->partnerCredentialsProvider->method('provide')->willThrowException(new PayPalPluginException('partner_id/partner_client_id is not configured'));
        $this->genericApi->expects(self::never())->method('get');

        self::assertNull($this->provider->provide($this->paymentMethod()));
    }

    public function test_it_returns_no_status_when_paypal_refuses_the_seller_credentials(): void
    {
        $this->partnerCredentialsProvider->method('provide')->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));
        $this->genericApi->method('get')->willThrowException(new PayPalApiAuthenticationException('POST', 'v1/oauth2/token', 401, 'invalid_client'));

        self::assertNull($this->provider->provide($this->paymentMethod()));
    }

    public function test_it_returns_no_status_when_no_partner_knows_the_merchant(): void
    {
        $this->partnerCredentialsProvider->method('provide')->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));
        $this->genericApi->method('get')->willThrowException(new PayPalApiNotFoundException('GET', 'v1/customer/partners/PARTNER-ID/merchant-integrations/MERCHANT-ID', 404));

        self::assertNull($this->provider->provide($this->paymentMethod()));
    }

    public function test_it_returns_no_status_for_a_payment_method_without_credentials(): void
    {
        $this->genericApi->expects(self::never())->method('get');

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig([]);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        self::assertNull($this->provider->provide($paymentMethod));
    }

    /** @param array<string, mixed> $extra */
    private function paymentMethod(array $extra = []): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig(array_merge(['client_id' => 'CLIENT-ID', 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT-ID', 'sandbox' => true], $extra));

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }

    /** @return array<string, mixed> */
    private function merchantIntegration(): array
    {
        return [
            'payments_receivable' => true,
            'primary_email_confirmed' => true,
            'capabilities' => [
                ['name' => 'APPLE_PAY', 'status' => 'ACTIVE'],
                ['name' => 'GOOGLE_PAY', 'status' => 'NEED_DATA'],
            ],
        ];
    }
}
