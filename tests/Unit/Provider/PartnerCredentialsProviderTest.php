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
    public function it_provides_the_configured_partner_credentials(): void
    {
        $provider = new PartnerCredentialsProvider('PARTNER-ID', 'PARTNER-CLIENT-ID', 'https://shop.example.com/logo.png');

        $credentials = $provider->provide();

        self::assertSame('PARTNER-ID', $credentials->getPartnerId());
        self::assertSame('PARTNER-CLIENT-ID', $credentials->getPartnerClientId());
        self::assertSame('https://shop.example.com/logo.png', $credentials->getPartnerLogoUrl());
    }

    #[Test]
    public function it_defaults_the_partner_logo_url_to_an_empty_string(): void
    {
        $provider = new PartnerCredentialsProvider('PARTNER-ID', 'PARTNER-CLIENT-ID');

        self::assertSame('', $provider->provide()->getPartnerLogoUrl());
    }

    #[Test]
    public function it_throws_when_the_partner_id_is_not_configured(): void
    {
        $provider = new PartnerCredentialsProvider('', 'PARTNER-CLIENT-ID');

        $this->expectException(PayPalPluginException::class);

        $provider->provide();
    }

    #[Test]
    public function it_throws_when_the_partner_client_id_is_not_configured(): void
    {
        $provider = new PartnerCredentialsProvider('PARTNER-ID', '');

        $this->expectException(PayPalPluginException::class);

        $provider->provide();
    }
}
