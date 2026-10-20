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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;

final readonly class PayPalVaultRequestProvider implements PayPalVaultRequestProviderInterface
{
    public function __construct(private VaultRequestResolverInterface $vaultRequestResolver)
    {
    }

    public function provide(PaymentInterface $payment): ?PayPalVaultRequest
    {
        $paymentMethod = $payment->getMethod();
        $gatewayConfig = $paymentMethod instanceof PaymentMethodInterface ? $paymentMethod->getGatewayConfig() : null;

        if (
            !$gatewayConfig instanceof GatewayConfigInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $gatewayConfig->getFactoryName() ||
            !PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isVaultingEnabled()
        ) {
            return null;
        }

        return $this->vaultRequestResolver->resolve($payment);
    }
}
