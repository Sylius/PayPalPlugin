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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Provider\CachingMerchantIntegrationStatusProvider;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusCacheInterface;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachingMerchantIntegrationStatusProviderTest extends TestCase
{
    private MerchantIntegrationStatusProviderInterface&MockObject $merchantIntegrationStatusProvider;

    private CachingMerchantIntegrationStatusProvider $provider;

    protected function setUp(): void
    {
        $this->merchantIntegrationStatusProvider = $this->createMock(MerchantIntegrationStatusProviderInterface::class);
        $this->provider = new CachingMerchantIntegrationStatusProvider($this->merchantIntegrationStatusProvider, new ArrayAdapter(), 3600, 300);
    }

    public function test_it_implements_the_merchant_integration_status_provider_interface(): void
    {
        self::assertInstanceOf(MerchantIntegrationStatusProviderInterface::class, $this->provider);
    }

    public function test_it_implements_the_merchant_integration_status_cache_interface(): void
    {
        self::assertInstanceOf(MerchantIntegrationStatusCacheInterface::class, $this->provider);
    }

    public function test_it_asks_paypal_once_and_then_answers_from_the_cache(): void
    {
        $status = new OnboardingStatus(true, true, ['APPLE_PAY']);
        $this->merchantIntegrationStatusProvider->expects(self::once())->method('provide')->willReturn($status);

        self::assertEquals($status, $this->provider->provide($this->paymentMethod('CLIENT-1', true)));
        self::assertEquals($status, $this->provider->provide($this->paymentMethod('CLIENT-1', true)));
    }

    public function test_it_asks_about_every_paypal_account_and_mode_on_its_own(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::exactly(3))->method('provide')->willReturn(new OnboardingStatus(true, true));

        $this->provider->provide($this->paymentMethod('CLIENT-1', true));
        $this->provider->provide($this->paymentMethod('CLIENT-2', true));
        $this->provider->provide($this->paymentMethod('CLIENT-1', false));
    }

    public function test_it_answers_an_unknown_status_from_the_cache(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::once())->method('provide')->willReturn(null);

        self::assertNull($this->provider->provide($this->paymentMethod('CLIENT-1', true)));
        self::assertNull($this->provider->provide($this->paymentMethod('CLIENT-1', true)));
    }

    public function test_it_keeps_an_unknown_status_for_a_shorter_while(): void
    {
        $this->merchantIntegrationStatusProvider->method('provide')->willReturn(null);
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->expects(self::once())->method('set')->with(null);
        $item->expects(self::once())->method('expiresAfter')->with(300);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->expects(self::once())->method('save')->with($item);
        $provider = new CachingMerchantIntegrationStatusProvider($this->merchantIntegrationStatusProvider, $cache, 3600, 300);

        self::assertNull($provider->provide($this->paymentMethod('CLIENT-1', true)));
    }

    public function test_it_asks_paypal_again_about_a_payment_method_whose_status_it_forgot(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::exactly(2))->method('provide')->willReturn(new OnboardingStatus(true, true));

        $this->provider->provide($this->paymentMethod('CLIENT-1', true));
        $this->provider->forget($this->paymentMethod('CLIENT-1', true));
        $this->provider->provide($this->paymentMethod('CLIENT-1', true));
    }

    public function test_it_keeps_the_status_of_other_paypal_accounts_when_it_forgets_one(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::exactly(2))->method('provide')->willReturn(new OnboardingStatus(true, true));

        $this->provider->provide($this->paymentMethod('CLIENT-1', true));
        $this->provider->provide($this->paymentMethod('CLIENT-2', true));
        $this->provider->forget($this->paymentMethod('CLIENT-2', true));
        $this->provider->provide($this->paymentMethod('CLIENT-1', true));
    }

    public function test_it_forgets_nothing_for_a_payment_method_without_credentials(): void
    {
        $this->provider->forget($this->paymentMethodWithConfig([]));

        $this->addToAssertionCount(1);
    }

    public function test_it_does_not_ask_about_a_payment_method_without_credentials(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::never())->method('provide');

        self::assertNull($this->provider->provide($this->paymentMethodWithConfig([])));
    }

    private function paymentMethod(string $clientId, bool $sandbox): PaymentMethodInterface
    {
        return $this->paymentMethodWithConfig(['client_id' => $clientId, 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT-ID', 'sandbox' => $sandbox]);
    }

    /** @param array<string, mixed> $config */
    private function paymentMethodWithConfig(array $config): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig($config);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
