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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProvider;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Resolver\NullVaultRequestResolver;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('sylius_paypal.resolver.vault_request', NullVaultRequestResolver::class);

    $services->alias(VaultRequestResolverInterface::class, 'sylius_paypal.resolver.vault_request');

    $services->set('sylius_paypal.provider.vault_request', PayPalVaultRequestProvider::class)
        ->args([
            service('sylius_paypal.resolver.vault_request'),
            service('sylius_paypal.resolver.paypal_payment_methods'),
        ]);

    $services->alias(PayPalVaultRequestProviderInterface::class, 'sylius_paypal.provider.vault_request');
};
