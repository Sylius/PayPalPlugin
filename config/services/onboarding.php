<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingCompletionProcessor;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingCompletionProcessorInterface;
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProvider;
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProviderInterface;
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
        ]);

    $services->alias(PayPalOnboardingUrlProviderInterface::class, 'sylius_paypal.provider.onboarding_url');

    $services->set('sylius_paypal.provider.onboarding_status_messages', OnboardingStatusMessagesProvider::class);

    $services->alias(OnboardingStatusMessagesProviderInterface::class, 'sylius_paypal.provider.onboarding_status_messages');

    $services->set('sylius_paypal.onboarding.processor.completion', OnboardingCompletionProcessor::class)
        ->args([
            service('sylius_paypal.provider.paypal_payment_method'),
            service('sylius_paypal.provider.seller_nonce'),
            service('sylius_paypal.onboarding.resolver.seller'),
            service('sylius_paypal.creator.onboarding_payment_method'),
            service('sylius_paypal.registrar.seller_webhook'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->alias(OnboardingCompletionProcessorInterface::class, 'sylius_paypal.onboarding.processor.completion');
};
