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

namespace Sylius\PayPalPlugin\Refresher;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class ThrottledVaultingCapabilityRefresher implements VaultingCapabilityRefresherInterface
{
    public function __construct(
        private VaultingCapabilityRefresherInterface $refresher,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private int $refreshCooldown,
    ) {
    }

    public function refresh(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (!$gatewayConfig instanceof GatewayConfigInterface) {
            return false;
        }

        $vaultingEnabled = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isVaultingEnabled();
        if ($vaultingEnabled || !$this->cooldownAllows($paymentMethod)) {
            return $vaultingEnabled;
        }

        try {
            return $this->refresher->refresh($paymentMethod);
        } catch (\Throwable $exception) {
            $this->logger->warning(sprintf(
                'Could not refresh the PayPal vaulting capability of "%s": %s',
                (string) $paymentMethod->getCode(),
                $exception->getMessage(),
            ));

            return false;
        }
    }

    private function cooldownAllows(PaymentMethodInterface $paymentMethod): bool
    {
        $item = $this->cache->getItem(
            'sylius_paypal_vaulting_capability_refresh_' . hash('sha256', (string) $paymentMethod->getCode()),
        );
        if ($item->isHit()) {
            return false;
        }

        $item->expiresAfter($this->refreshCooldown);
        $this->cache->save($item);

        return true;
    }
}
