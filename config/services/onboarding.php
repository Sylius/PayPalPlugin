<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProvider;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Provider\SellerNonceProvider;
use Sylius\PayPalPlugin\Provider\SellerNonceProviderInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_paypal.provider.seller_nonce', SellerNonceProvider::class)
        ->args([
            service('request_stack'),
        ]);

    $services->alias(SellerNonceProviderInterface::class, 'sylius_paypal.provider.seller_nonce');

    $services->set('sylius_paypal.provider.onboarding_url', PayPalOnboardingUrlProvider::class)
        ->args([
            '%sylius_paypal.web_url%',
            '%sylius_paypal.partner_id%',
            '%sylius_paypal.partner_client_id%',
            '%sylius_paypal.partner_logo_url%',
            service('router'),
        ]);

    $services->alias(PayPalOnboardingUrlProviderInterface::class, 'sylius_paypal.provider.onboarding_url');
};
