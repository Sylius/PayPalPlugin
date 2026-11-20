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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Checker\VaultingCapabilityCheckerInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalVaultRequirement;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;

final readonly class PayPalVaultRequestProvider implements PayPalVaultRequestProviderInterface
{
    public function __construct(
        private VaultRequestResolverInterface $vaultRequestResolver,
        private VaultingCapabilityCheckerInterface $vaultingCapabilityChecker,
    ) {
    }

    public function provide(OrderInterface $order, ?PaymentMethodInterface $paymentMethod): PayPalVaultRequirement
    {
        if (null === $paymentMethod) {
            return PayPalVaultRequirement::notRequired();
        }

        $gatewayConfig = $this->payPalGatewayConfig($paymentMethod);
        if (null === $gatewayConfig) {
            return PayPalVaultRequirement::notRequired();
        }

        $vaultRequest = $this->vaultRequestResolver->resolve($order);
        if (null === $vaultRequest) {
            return PayPalVaultRequirement::notRequired();
        }

        if (!$this->canVault($gatewayConfig, $paymentMethod)) {
            return PayPalVaultRequirement::unmet();
        }

        return PayPalVaultRequirement::met($vaultRequest);
    }

    private function canVault(PayPalGatewayConfig $gatewayConfig, PaymentMethodInterface $paymentMethod): bool
    {
        return $gatewayConfig->isVaultingEnabled() || $this->vaultingCapabilityChecker->isVaultingActive($paymentMethod);
    }

    private function payPalGatewayConfig(PaymentMethodInterface $paymentMethod): ?PayPalGatewayConfig
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (
            !$gatewayConfig instanceof GatewayConfigInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $gatewayConfig->getFactoryName()
        ) {
            return null;
        }

        return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
    }
}
