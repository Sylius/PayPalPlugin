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
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Provider\HostProvider;
use Sylius\PayPalPlugin\Provider\WebSdkConfigurationProvider;

final class WebSdkConfigurationProviderTest extends TestCase
{
    #[Test]
    public function it_builds_the_v6_web_sdk_script_url_on_the_host_of_the_mode_of_the_payment_method(): void
    {
        $provider = $this->createProvider(null);

        self::assertSame('https://www.sandbox.paypal.com/web-sdk/v6/core', $provider->getScriptUrl($this->paymentMethod(sandbox: true)));
        self::assertSame('https://www.paypal.com/web-sdk/v6/core', $provider->getScriptUrl($this->paymentMethod(sandbox: false)));
    }

    #[Test]
    public function it_only_includes_paypal_payments_by_default(): void
    {
        $config = $this->createProvider(null)->getInstanceConfig($this->paymentMethod(), 'checkout');

        self::assertSame(['paypal-payments'], $config['components']);
        self::assertSame('CLIENT_ID', $config['clientId']);
        self::assertSame('Sylius_MP_PPCP', $config['partnerAttributionId']);
        self::assertSame('checkout', $config['pageType']);
    }

    #[Test]
    public function it_builds_the_instance_config_with_the_given_components(): void
    {
        $config = $this->createProvider(null)->getInstanceConfig($this->paymentMethod(), 'checkout', ['paypal-payments', 'card-fields']);

        self::assertSame(['paypal-payments', 'card-fields'], $config['components']);
    }

    #[Test]
    public function it_builds_the_instance_config_with_the_given_locale(): void
    {
        $config = $this->createProvider(null)->getInstanceConfig($this->paymentMethod(), 'checkout', locale: 'en-US');

        self::assertSame('en-US', $config['locale']);
    }

    #[Test]
    public function it_hands_the_sdk_a_locale_it_understands(): void
    {
        $config = $this->createProvider(null)->getInstanceConfig($this->paymentMethod(), 'checkout', locale: 'en_US');

        self::assertSame('en-US', $config['locale']);
    }

    #[Test]
    public function it_builds_the_instance_config_without_a_locale_when_none_is_given(): void
    {
        $config = $this->createProvider(null)->getInstanceConfig($this->paymentMethod(), 'checkout');

        self::assertArrayNotHasKey('locale', $config);
    }

    #[Test]
    public function it_includes_the_test_buyer_country_for_a_sandbox_payment_method_when_configured(): void
    {
        $config = $this->createProvider('US')->getInstanceConfig($this->paymentMethod(sandbox: true), 'cart');

        self::assertSame('US', $config['testBuyerCountry']);
    }

    #[Test]
    public function it_omits_the_test_buyer_country_for_a_sandbox_payment_method_when_not_configured(): void
    {
        $config = $this->createProvider(null)->getInstanceConfig($this->paymentMethod(sandbox: true), 'cart');

        self::assertArrayNotHasKey('testBuyerCountry', $config);
    }

    #[Test]
    public function it_never_includes_the_test_buyer_country_for_a_live_payment_method_even_when_configured(): void
    {
        $config = $this->createProvider('US')->getInstanceConfig($this->paymentMethod(sandbox: false), 'cart');

        self::assertArrayNotHasKey('testBuyerCountry', $config);
    }

    private function createProvider(?string $testBuyerCountry): WebSdkConfigurationProvider
    {
        return new WebSdkConfigurationProvider(
            new HostProvider(),
            $testBuyerCountry,
        );
    }

    private function paymentMethod(bool $sandbox = true): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig([
            'client_id' => 'CLIENT_ID',
            'partner_attribution_id' => 'Sylius_MP_PPCP',
            'sandbox' => $sandbox,
        ]);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
