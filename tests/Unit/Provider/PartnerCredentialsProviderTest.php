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
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProvider;

final class PartnerCredentialsProviderTest extends TestCase
{
    #[Test]
    public function it_provides_the_production_partner_credentials_in_production_mode(): void
    {
        $credentials = $this->provider()->provide(false);

        self::assertSame('PARTNER-ID', $credentials->getPartnerId());
        self::assertSame('PARTNER-CLIENT-ID', $credentials->getPartnerClientId());
        self::assertSame('https://shop.example.com/logo.png', $credentials->getPartnerLogoUrl());
    }

    #[Test]
    public function it_provides_the_sandbox_partner_credentials_in_sandbox_mode(): void
    {
        $credentials = $this->provider()->provide(true);

        self::assertSame('SANDBOX-PARTNER-ID', $credentials->getPartnerId());
        self::assertSame('SANDBOX-PARTNER-CLIENT-ID', $credentials->getPartnerClientId());
        self::assertSame('https://shop.example.com/logo.png', $credentials->getPartnerLogoUrl());
    }

    #[Test]
    public function it_defaults_the_partner_logo_url_to_an_empty_string(): void
    {
        $provider = new PartnerCredentialsProvider('PARTNER-ID', 'PARTNER-CLIENT-ID', '', '');

        self::assertSame('', $provider->provide(false)->getPartnerLogoUrl());
    }

    #[Test]
    public function it_throws_when_the_partner_id_is_not_configured(): void
    {
        $provider = new PartnerCredentialsProvider('', 'PARTNER-CLIENT-ID', 'SANDBOX-PARTNER-ID', 'SANDBOX-PARTNER-CLIENT-ID');

        $this->expectException(PayPalPluginException::class);

        $provider->provide(false);
    }

    #[Test]
    public function it_throws_when_the_sandbox_partner_client_id_is_not_configured(): void
    {
        $provider = new PartnerCredentialsProvider('PARTNER-ID', 'PARTNER-CLIENT-ID', 'SANDBOX-PARTNER-ID', '');

        $this->expectException(PayPalPluginException::class);

        $provider->provide(true);
    }

    private function provider(): PartnerCredentialsProvider
    {
        return new PartnerCredentialsProvider(
            'PARTNER-ID',
            'PARTNER-CLIENT-ID',
            'SANDBOX-PARTNER-ID',
            'SANDBOX-PARTNER-CLIENT-ID',
            'https://shop.example.com/logo.png',
        );
    }
}
