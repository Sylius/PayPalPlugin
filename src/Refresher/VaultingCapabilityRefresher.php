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

use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Checker\VaultingCapabilityCheckerInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class VaultingCapabilityRefresher implements VaultingCapabilityRefresherInterface
{
    public function __construct(private VaultingCapabilityCheckerInterface $vaultingCapabilityChecker)
    {
    }

    public function refresh(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (!$gatewayConfig instanceof GatewayConfigInterface) {
            return false;
        }

        $vaultingEnabled = $this->vaultingCapabilityChecker->isVaultingActive($paymentMethod);
        if ($vaultingEnabled !== PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isVaultingEnabled()) {
            $gatewayConfig->setConfig(array_merge(
                $gatewayConfig->getConfig(),
                [PayPalGatewayConfig::VAULTING_ENABLED => $vaultingEnabled],
            ));
        }

        return $vaultingEnabled;
    }
}
