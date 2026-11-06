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

namespace Sylius\PayPalPlugin\Provider;

use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class CachingMerchantIntegrationStatusProvider implements MerchantIntegrationStatusProviderInterface, MerchantIntegrationStatusCacheInterface
{
    public function __construct(
        private MerchantIntegrationStatusProviderInterface $decorated,
        private CacheItemPoolInterface $cache,
        private int $lifetime,
        private int $unknownLifetime,
    ) {
    }

    public function provide(PaymentMethodInterface $paymentMethod): ?OnboardingStatus
    {
        $config = $this->configOf($paymentMethod);
        if (!$config->hasClientId()) {
            return null;
        }

        $item = $this->cache->getItem($this->key($config));
        if ($item->isHit()) {
            /** @var array{payments_receivable?: bool, primary_email_confirmed?: bool, active_capabilities?: list<string>}|null $cached */
            $cached = $item->get();

            return null === $cached ? null : OnboardingStatus::fromArray($cached);
        }

        $status = $this->decorated->provide($paymentMethod);

        $item->set($status?->toArray());
        $item->expiresAfter(null === $status ? $this->unknownLifetime : $this->lifetime);
        $this->cache->save($item);

        return $status;
    }

    public function forget(PaymentMethodInterface $paymentMethod): void
    {
        $config = $this->configOf($paymentMethod);
        if ($config->hasClientId()) {
            $this->cache->deleteItem($this->key($config));
        }
    }

    private function configOf(PaymentMethodInterface $paymentMethod): PayPalGatewayConfig
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
    }

    private function key(PayPalGatewayConfig $config): string
    {
        return 'sylius_paypal_merchant_integration_status_' . hash('sha256', implode('|', [
            $config->isSandbox() ? 'sandbox' : 'production',
            $config->clientId(),
        ]));
    }
}
