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

use Sylius\Component\Core\Model\PaymentMethodInterface as CorePaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;

final readonly class VaultingCapabilityChecker implements VaultingCapabilityCheckerInterface
{
    public function __construct(private MerchantIntegrationStatusProviderInterface $merchantIntegrationStatusProvider)
    {
    }

    public function isVaultingActive(PaymentMethodInterface $paymentMethod): bool
    {
        if (
            !$paymentMethod instanceof CorePaymentMethodInterface ||
            !$paymentMethod->getGatewayConfig() instanceof GatewayConfigInterface
        ) {
            return false;
        }

        return $this->merchantIntegrationStatusProvider->provide($paymentMethod)?->hasActiveCapability(Capability::PAYPAL_WALLET_VAULTING_ADVANCED) ?? false;
    }
}
