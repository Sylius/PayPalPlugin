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

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Provider\PersistingWebhookIdProvider;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;

final class PersistingWebhookIdProviderTest extends TestCase
{
    private const COOLDOWN = 300;

    private WebhookIdProviderInterface&MockObject $innerProvider;

    private ObjectManager&MockObject $paymentMethodManager;

    private CacheItemPoolInterface&MockObject $cache;

    private PersistingWebhookIdProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->innerProvider = $this->createMock(WebhookIdProviderInterface::class);
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);

        $this->provider = new PersistingWebhookIdProvider(
            $this->innerProvider,
            $this->paymentMethodManager,
            $this->cache,
            self::COOLDOWN,
        );
    }

    #[Test]
    public function it_implements_webhook_id_provider_interface(): void
    {
        self::assertInstanceOf(WebhookIdProviderInterface::class, $this->provider);
    }

    #[Test]
    public function it_returns_the_webhook_id_stored_in_the_gateway_config_without_querying(): void
    {
        $paymentMethod = $this->paymentMethod($this->gatewayConfig(['webhook_id' => 'WH-STORED']));

        $this->innerProvider->expects(self::never())->method('provide');
        $this->paymentMethodManager->expects(self::never())->method('flush');

        self::assertSame('WH-STORED', $this->provider->provide($paymentMethod));
    }

    #[Test]
    public function it_resolves_and_stores_the_webhook_id_when_it_is_missing_from_the_config(): void
    {
        $gatewayConfig = $this->gatewayConfig([]);
        $paymentMethod = $this->paymentMethod($gatewayConfig);

        $this->innerProvider->method('provide')->with($paymentMethod)->willReturn('WH-NEW');
        $gatewayConfig->expects(self::once())->method('setConfig')->with(['webhook_id' => 'WH-NEW']);
        $this->paymentMethodManager->expects(self::once())->method('flush');

        self::assertSame('WH-NEW', $this->provider->provide($paymentMethod));
    }

    #[Test]
    public function it_returns_null_and_stores_nothing_when_the_lookup_finds_no_webhook(): void
    {
        $gatewayConfig = $this->gatewayConfig([]);
        $paymentMethod = $this->paymentMethod($gatewayConfig);

        $this->innerProvider->method('provide')->willReturn(null);
        $gatewayConfig->expects(self::never())->method('setConfig');
        $this->paymentMethodManager->expects(self::never())->method('flush');

        self::assertNull($this->provider->provide($paymentMethod));
    }

    #[Test]
    public function it_refreshes_from_a_live_lookup_and_stores_it_when_cooldown_allows(): void
    {
        $gatewayConfig = $this->gatewayConfig(['webhook_id' => 'WH-STALE']);
        $paymentMethod = $this->paymentMethod($gatewayConfig);

        $this->mockCooldown(isHit: false);
        $this->innerProvider->expects(self::once())->method('refresh')->with($paymentMethod)->willReturn('WH-FRESH');
        $gatewayConfig->expects(self::once())->method('setConfig')->with(['webhook_id' => 'WH-FRESH']);
        $this->paymentMethodManager->expects(self::once())->method('flush');

        self::assertSame('WH-FRESH', $this->provider->refresh($paymentMethod));
    }

    #[Test]
    public function it_does_not_refresh_while_within_the_cooldown_window(): void
    {
        $paymentMethod = $this->paymentMethod($this->gatewayConfig(['webhook_id' => 'WH-STALE']));

        $this->mockCooldown(isHit: true);
        $this->innerProvider->expects(self::never())->method('refresh');
        $this->paymentMethodManager->expects(self::never())->method('flush');

        self::assertNull($this->provider->refresh($paymentMethod));
    }

    private function mockCooldown(bool $isHit): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn($isHit);
        $this->cache->method('getItem')->willReturn($item);
    }

    private function gatewayConfig(array $config): GatewayConfigInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn($config);

        return $gatewayConfig;
    }

    private function paymentMethod(GatewayConfigInterface $gatewayConfig): PaymentMethodInterface&MockObject
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $paymentMethod->method('getCode')->willReturn('paypal');

        return $paymentMethod;
    }
}
