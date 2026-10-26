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

use Sylius\PayPalPlugin\Console\Command\RefreshVaultingCapabilityCommand;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProvider;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Refresher\ThrottledVaultingCapabilityRefresher;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresher;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Sylius\PayPalPlugin\Resolver\NullVaultRequestResolver;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;
use Sylius\PayPalPlugin\Resolver\VaultedChargeFailureResolver;
use Sylius\PayPalPlugin\Resolver\VaultedChargeFailureResolverInterface;

return static function (ContainerConfigurator $container): void {
    $parameters = $container->parameters();
    $parameters->set('sylius_paypal.vaulting_capability_refresh_cooldown', 3600);

    $services = $container->services();

    $services->set('sylius_paypal.resolver.vault_request', NullVaultRequestResolver::class);

    $services->alias(VaultRequestResolverInterface::class, 'sylius_paypal.resolver.vault_request');

    $services->set('sylius_paypal.provider.vault_request', PayPalVaultRequestProvider::class)
        ->args([
            service('sylius_paypal.resolver.vault_request'),
            service('sylius_paypal.resolver.paypal_payment_methods'),
            service('sylius_paypal.refresher.vaulting_capability.throttled'),
        ]);

    $services->alias(PayPalVaultRequestProviderInterface::class, 'sylius_paypal.provider.vault_request');

    $services->set('sylius_paypal.refresher.vaulting_capability', VaultingCapabilityRefresher::class)
        ->args([
            service('sylius_paypal.api.authorize_client'),
            service('sylius_paypal.api.merchant_onboarding_status'),
            service('sylius_paypal.provider.partner_credentials'),
            service('sylius.manager.payment_method'),
        ]);

    $services->alias(VaultingCapabilityRefresherInterface::class, 'sylius_paypal.refresher.vaulting_capability');

    $services->set('sylius_paypal.refresher.vaulting_capability.throttled', ThrottledVaultingCapabilityRefresher::class)
        ->args([
            service('sylius_paypal.refresher.vaulting_capability'),
            service('sylius_paypal.cache'),
            service('monolog.logger.paypal'),
            param('sylius_paypal.vaulting_capability_refresh_cooldown'),
        ]);

    $services->set('sylius_paypal.console.command.refresh_vaulting_capability', RefreshVaultingCapabilityCommand::class)
        ->args([
            service('sylius.repository.payment_method'),
            service('sylius_paypal.refresher.vaulting_capability'),
        ])
        ->tag('console.command');

    $services->set('sylius_paypal.resolver.vaulted_charge_failure', VaultedChargeFailureResolver::class);

    $services->alias(VaultedChargeFailureResolverInterface::class, 'sylius_paypal.resolver.vaulted_charge_failure');
};
