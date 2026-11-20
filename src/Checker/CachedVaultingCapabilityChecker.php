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

namespace Sylius\PayPalPlugin\Checker;

use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class CachedVaultingCapabilityChecker implements VaultingCapabilityCheckerInterface
{
    public function __construct(
        private VaultingCapabilityCheckerInterface $checker,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private int $cooldown,
        private int $retryCooldown,
    ) {
    }

    public function isVaultingActive(PaymentMethodInterface $paymentMethod): bool
    {
        return true === $this->cache->get(
            $this->cacheKey($paymentMethod),
            function (ItemInterface $item) use ($paymentMethod): bool {
                try {
                    $vaultingActive = $this->checker->isVaultingActive($paymentMethod);
                } catch (\Throwable $exception) {
                    $item->expiresAfter($this->retryCooldown);
                    $this->logger->warning(sprintf(
                        'Could not check the PayPal vaulting capability of "%s": %s',
                        $paymentMethod->getCode(),
                        $exception->getMessage(),
                    ));

                    return false;
                }

                $item->expiresAfter($vaultingActive ? $this->cooldown : $this->retryCooldown);

                return $vaultingActive;
            },
        );
    }

    private function cacheKey(PaymentMethodInterface $paymentMethod): string
    {
        $config = $paymentMethod->getGatewayConfig()?->getConfig() ?? [];

        return 'sylius_paypal_vaulting_capability_' . hash('sha256', serialize([
            $paymentMethod->getCode(),
            $config[PayPalGatewayConfig::MERCHANT_ID] ?? null,
            PayPalGatewayConfig::fromArray($config)->isSandbox(),
        ]));
    }
}
