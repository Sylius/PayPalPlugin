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
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class CachingMerchantIntegrationStatusProvider implements MerchantIntegrationStatusProviderInterface
{
    public function __construct(
        private MerchantIntegrationStatusProviderInterface $decorated,
        private CacheItemPoolInterface $cache,
        private int $lifetime,
    ) {
    }

    public function provide(PayPalGatewayConfig $config): ?OnboardingStatus
    {
        if (!$config->hasClientId()) {
            return null;
        }

        $item = $this->cache->getItem($this->key($config));
        if ($item->isHit()) {
            /** @var array{payments_receivable?: bool, primary_email_confirmed?: bool, active_capabilities?: list<string>} $cached */
            $cached = $item->get();

            return OnboardingStatus::fromArray($cached);
        }

        $status = $this->decorated->provide($config);
        if (null === $status) {
            return null;
        }

        $item->set($status->toArray());
        $item->expiresAfter($this->lifetime);
        $this->cache->save($item);

        return $status;
    }

    private function key(PayPalGatewayConfig $config): string
    {
        return 'sylius_paypal_merchant_integration_status_' . hash('sha256', implode('|', [
            $config->isSandbox() ? 'sandbox' : 'production',
            $config->clientId(),
        ]));
    }
}
