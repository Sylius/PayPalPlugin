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
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;

final readonly class PayPalVaultRequestProvider implements PayPalVaultRequestProviderInterface
{
    public function __construct(private VaultRequestResolverInterface $vaultRequestResolver)
    {
    }

    public function provide(OrderInterface $order, ?PaymentMethodInterface $paymentMethod): ?PayPalVaultRequest
    {
        $gatewayConfig = $paymentMethod?->getGatewayConfig();

        if (
            !$gatewayConfig instanceof GatewayConfigInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $gatewayConfig->getFactoryName() ||
            !PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isVaultingEnabled()
        ) {
            return null;
        }

        return $this->vaultRequestResolver->resolve($order);
    }
}
