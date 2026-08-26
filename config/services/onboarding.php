<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Http\Message\RequestFactoryInterface;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiator;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Sylius\PayPalPlugin\Onboarding\Processor\BasicOnboardingProcessor;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProvider;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Provider\SellerNonceProvider;
use Sylius\PayPalPlugin\Provider\SellerNonceProviderInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_paypal.onboarding.initiator', OnboardingInitiator::class)
        ->args([
            service('router'),
            service('security.helper'),
            '%sylius_paypal.facilitator_url%',
        ]);

    $services->alias(OnboardingInitiatorInterface::class, 'sylius_paypal.onboarding.initiator');

    $services->set('sylius_paypal.onboarding.processor.basic', BasicOnboardingProcessor::class)
        ->args([
            service('sylius.http_client'),
            service('sylius_paypal.registrar.seller_webhook'),
            '%sylius_paypal.facilitator_url%',
            service(RequestFactoryInterface::class),
        ]);

    $services->alias(OnboardingProcessorInterface::class, 'sylius_paypal.onboarding.processor.basic');

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
