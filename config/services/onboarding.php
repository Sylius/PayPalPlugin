<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\PayPalPlugin\Provider\PartnerCredentialsProvider;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;
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

    $services->set('sylius_paypal.provider.partner_credentials', PartnerCredentialsProvider::class)
        ->args([
            param('sylius_paypal.partner_credentials.partner_id'),
            param('sylius_paypal.partner_credentials.partner_client_id'),
            param('sylius_paypal.partner_credentials.logo_url'),
        ]);

    $services->alias(PartnerCredentialsProviderInterface::class, 'sylius_paypal.provider.partner_credentials');

    $services->set('sylius_paypal.provider.onboarding_url', PayPalOnboardingUrlProvider::class)
        ->args([
            param('sylius_paypal.web_url'),
            service('sylius_paypal.provider.partner_credentials'),
            service('router'),
        ]);

    $services->alias(PayPalOnboardingUrlProviderInterface::class, 'sylius_paypal.provider.onboarding_url');
};
