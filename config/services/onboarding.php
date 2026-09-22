<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Http\Message\RequestFactoryInterface;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiator;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Sylius\PayPalPlugin\Onboarding\Processor\BasicOnboardingProcessor;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingProcessorInterface;

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
};
