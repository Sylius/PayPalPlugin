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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;

final class PayPalGatewayConfigTest extends TestCase
{
    public function test_it_reads_the_credentials_the_api_calls_need(): void
    {
        $config = PayPalGatewayConfig::fromArray([
            'client_id' => 'CLIENT_ID',
            'client_secret' => 'CLIENT_SECRET',
            'merchant_id' => 'MERCHANT_ID',
            'sylius_merchant_id' => 'SYLIUS_MERCHANT_ID',
            'partner_attribution_id' => 'PARTNER_ATTRIBUTION_ID',
        ]);

        self::assertSame('CLIENT_ID', $config->clientId());
        self::assertSame('CLIENT_SECRET', $config->clientSecret());
        self::assertSame('MERCHANT_ID', $config->merchantId());
        self::assertSame('SYLIUS_MERCHANT_ID', $config->syliusMerchantId());
        self::assertSame('PARTNER_ATTRIBUTION_ID', $config->partnerAttributionId());
    }

    public function test_it_reads_the_config_off_a_gateway_config(): void
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['client_id' => 'CLIENT_ID']);

        self::assertSame('CLIENT_ID', PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->clientId());
    }

    public function test_it_refuses_to_read_a_credential_the_gateway_does_not_carry(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PayPalGatewayConfig::fromArray([])->clientId();
    }

    public function test_it_tells_an_unonboarded_gateway_apart_from_an_onboarded_one(): void
    {
        self::assertFalse(PayPalGatewayConfig::fromArray([])->hasClientId());
        self::assertTrue(PayPalGatewayConfig::fromArray(['client_id' => 'CLIENT_ID'])->hasClientId());

        self::assertFalse(PayPalGatewayConfig::fromArray([])->hasPartnerAttributionId());
        self::assertTrue(PayPalGatewayConfig::fromArray(['partner_attribution_id' => 'BN'])->hasPartnerAttributionId());

        self::assertFalse(PayPalGatewayConfig::fromArray([])->hasSyliusMerchantId());
        self::assertTrue(PayPalGatewayConfig::fromArray(['sylius_merchant_id' => 'ID'])->hasSyliusMerchantId());
    }

    public function test_it_reads_the_optional_values_as_null_when_they_are_not_set(): void
    {
        $config = PayPalGatewayConfig::fromArray([]);

        self::assertNull($config->reportsSftpUsername());
        self::assertNull($config->reportsSftpPassword());
        self::assertNull($config->webhookId());
    }

    public function test_it_reads_the_optional_values_when_they_are_set(): void
    {
        $config = PayPalGatewayConfig::fromArray([
            'reports_sftp_username' => 'USERNAME',
            'reports_sftp_password' => 'PASSWORD',
            'webhook_id' => 'WEBHOOK_ID',
        ]);

        self::assertSame('USERNAME', $config->reportsSftpUsername());
        self::assertSame('PASSWORD', $config->reportsSftpPassword());
        self::assertSame('WEBHOOK_ID', $config->webhookId());
    }

    public function test_it_opts_a_gateway_into_pay_later_and_messaging_until_told_otherwise(): void
    {
        $config = PayPalGatewayConfig::fromArray([]);

        self::assertTrue($config->isPayLaterEnabled());
        self::assertTrue($config->isMessagingEnabled());
    }

    public function test_it_keeps_the_wallets_and_the_redirect_methods_off_until_told_otherwise(): void
    {
        $config = PayPalGatewayConfig::fromArray([]);

        self::assertFalse($config->isVenmoEnabled());
        self::assertFalse($config->isGooglePayEnabled());
        self::assertFalse($config->isApplePayEnabled());
        self::assertFalse($config->isRedirectPaymentSourceEnabled(RedirectPaymentSource::Trustly));
    }

    public function test_it_reads_every_funding_source_flag_the_gateway_carries(): void
    {
        $config = PayPalGatewayConfig::fromArray([
            'pay_later_enabled' => false,
            'messaging_enabled' => false,
            'venmo_enabled' => true,
            'google_pay_enabled' => true,
            'apple_pay_enabled' => true,
            'trustly_enabled' => true,
        ]);

        self::assertFalse($config->isPayLaterEnabled());
        self::assertFalse($config->isMessagingEnabled());
        self::assertTrue($config->isVenmoEnabled());
        self::assertTrue($config->isGooglePayEnabled());
        self::assertTrue($config->isApplePayEnabled());
        self::assertTrue($config->isRedirectPaymentSourceEnabled(RedirectPaymentSource::Trustly));
    }

    public function test_it_takes_the_redirect_payment_source_key_from_the_enum(): void
    {
        $config = PayPalGatewayConfig::fromArray([
            RedirectPaymentSource::Trustly->configurationKey() => true,
        ]);

        self::assertTrue($config->isRedirectPaymentSourceEnabled(RedirectPaymentSource::Trustly));
    }
}
