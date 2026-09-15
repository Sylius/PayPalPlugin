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
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebSdkConfigurationProvider;

final class PayPalWebSdkConfigurationProviderTest extends TestCase
{
    private PayPalConfigurationProviderInterface&MockObject $payPalConfigurationProvider;

    private PayPalWebSdkConfigurationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalConfigurationProvider = $this->createMock(PayPalConfigurationProviderInterface::class);

        $this->provider = new PayPalWebSdkConfigurationProvider(
            $this->payPalConfigurationProvider,
            'https://www.sandbox.paypal.com',
        );
    }

    #[Test]
    public function it_builds_the_v6_web_sdk_script_url_from_the_configured_web_url(): void
    {
        self::assertSame('https://www.sandbox.paypal.com/web-sdk/v6/core', $this->provider->getScriptUrl());
    }

    #[Test]
    public function it_builds_the_instance_config_for_the_given_channel_and_page_type(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->payPalConfigurationProvider->method('getClientId')->with($channel)->willReturn('CLIENT_ID');
        $this->payPalConfigurationProvider->method('getPartnerAttributionId')->with($channel)->willReturn('Sylius_MP_PPCP');

        $config = $this->provider->getInstanceConfig($channel, 'checkout');

        self::assertSame(['paypal-payments'], $config['components']);
        self::assertSame('CLIENT_ID', $config['clientId']);
        self::assertSame('Sylius_MP_PPCP', $config['partnerAttributionId']);
        self::assertSame('checkout', $config['pageType']);
    }

    #[Test]
    public function it_builds_the_instance_config_with_the_given_components(): void
    {
        $config = $this->provider->getInstanceConfig(
            $this->createMock(ChannelInterface::class),
            'checkout',
            ['paypal-payments', 'card-fields'],
        );

        self::assertSame(['paypal-payments', 'card-fields'], $config['components']);
    }

    #[Test]
    public function it_builds_the_instance_config_with_the_given_locale(): void
    {
        $config = $this->provider->getInstanceConfig(
            $this->createMock(ChannelInterface::class),
            'checkout',
            locale: 'en-US',
        );

        self::assertSame('en-US', $config['locale']);
    }

    #[Test]
    public function it_hands_the_sdk_a_locale_it_understands(): void
    {
        $config = $this->provider->getInstanceConfig(
            $this->createMock(ChannelInterface::class),
            'checkout',
            locale: 'en_US',
        );

        self::assertSame('en-US', $config['locale']);
    }

    #[Test]
    public function it_builds_the_instance_config_without_a_locale_when_none_is_given(): void
    {
        $config = $this->provider->getInstanceConfig($this->createMock(ChannelInterface::class), 'checkout');

        self::assertArrayNotHasKey('locale', $config);
    }
}
