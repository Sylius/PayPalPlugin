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
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\CachingMerchantIntegrationStatusProvider;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachingMerchantIntegrationStatusProviderTest extends TestCase
{
    private MerchantIntegrationStatusProviderInterface&MockObject $merchantIntegrationStatusProvider;

    private CachingMerchantIntegrationStatusProvider $provider;

    protected function setUp(): void
    {
        $this->merchantIntegrationStatusProvider = $this->createMock(MerchantIntegrationStatusProviderInterface::class);
        $this->provider = new CachingMerchantIntegrationStatusProvider($this->merchantIntegrationStatusProvider, new ArrayAdapter(), 3600);
    }

    public function test_it_implements_the_merchant_integration_status_provider_interface(): void
    {
        self::assertInstanceOf(MerchantIntegrationStatusProviderInterface::class, $this->provider);
    }

    public function test_it_asks_paypal_once_and_then_answers_from_the_cache(): void
    {
        $status = new OnboardingStatus(true, true, ['APPLE_PAY']);
        $this->merchantIntegrationStatusProvider->expects(self::once())->method('provide')->willReturn($status);

        self::assertEquals($status, $this->provider->provide($this->config('CLIENT-1', true)));
        self::assertEquals($status, $this->provider->provide($this->config('CLIENT-1', true)));
    }

    public function test_it_asks_about_every_paypal_account_and_mode_on_its_own(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::exactly(3))->method('provide')->willReturn(new OnboardingStatus(true, true));

        $this->provider->provide($this->config('CLIENT-1', true));
        $this->provider->provide($this->config('CLIENT-2', true));
        $this->provider->provide($this->config('CLIENT-1', false));
    }

    public function test_it_does_not_cache_an_unknown_status(): void
    {
        $status = new OnboardingStatus(true, true);
        $this->merchantIntegrationStatusProvider
            ->expects(self::exactly(2))
            ->method('provide')
            ->willReturnOnConsecutiveCalls(null, $status)
        ;

        self::assertNull($this->provider->provide($this->config('CLIENT-1', true)));
        self::assertEquals($status, $this->provider->provide($this->config('CLIENT-1', true)));
    }

    public function test_it_does_not_ask_about_a_payment_method_without_credentials(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::never())->method('provide');

        self::assertNull($this->provider->provide(PayPalGatewayConfig::fromArray([])));
    }

    private function config(string $clientId, bool $sandbox): PayPalGatewayConfig
    {
        return PayPalGatewayConfig::fromArray(['client_id' => $clientId, 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT-ID', 'sandbox' => $sandbox]);
    }
}
