<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApi;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Api\OnboardingTokenApi;
use Sylius\PayPalPlugin\Api\OnboardingTokenApiInterface;
use Sylius\PayPalPlugin\Api\PayPalOnboardingRequestExecutor;
use Sylius\PayPalPlugin\Api\PayPalOnboardingRequestExecutorInterface;
use Sylius\PayPalPlugin\Api\SellerCredentialsApi;
use Sylius\PayPalPlugin\Api\SellerCredentialsApiInterface;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolver;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $parameters->set('sylius_paypal.onboarding.http_client.timeout', 10);
    $parameters->set('sylius_paypal.onboarding.http_client.max_duration', 30);

    $services->set('sylius_paypal.http_client.onboarding', HttpClientInterface::class)
        ->private()
        ->factory([HttpClient::class, 'create'])
        ->args([[
            'timeout' => param('sylius_paypal.onboarding.http_client.timeout'),
            'max_duration' => param('sylius_paypal.onboarding.http_client.max_duration'),
        ]]);

    $services->set('sylius_paypal.psr18_client.onboarding', Psr18Client::class)
        ->private()
        ->args([
            service('sylius_paypal.http_client.onboarding'),
        ]);

    $services->set('sylius_paypal.api.onboarding_request_executor', PayPalOnboardingRequestExecutor::class)
        ->args([
            service('sylius_paypal.psr18_client.onboarding'),
            service('monolog.logger.paypal'),
            param('sylius_paypal.partner_attribution_id'),
        ]);

    $services->alias(PayPalOnboardingRequestExecutorInterface::class, 'sylius_paypal.api.onboarding_request_executor');

    $services->set('sylius_paypal.api.onboarding_token', OnboardingTokenApi::class)
        ->args([
            service('sylius_paypal.api.onboarding_request_executor'),
            param('sylius_paypal.api_base_url'),
            service(RequestFactoryInterface::class),
            service(StreamFactoryInterface::class),
        ]);

    $services->alias(OnboardingTokenApiInterface::class, 'sylius_paypal.api.onboarding_token');

    $services->set('sylius_paypal.api.seller_credentials', SellerCredentialsApi::class)
        ->args([
            service('sylius_paypal.api.onboarding_request_executor'),
            param('sylius_paypal.api_base_url'),
            service(RequestFactoryInterface::class),
        ]);

    $services->alias(SellerCredentialsApiInterface::class, 'sylius_paypal.api.seller_credentials');

    $services->set('sylius_paypal.api.merchant_onboarding_status', MerchantOnboardingStatusApi::class)
        ->args([
            service('sylius_paypal.api.onboarding_request_executor'),
            service(RequestFactoryInterface::class),
            param('sylius_paypal.api_base_url'),
        ]);

    $services->alias(MerchantOnboardingStatusApiInterface::class, 'sylius_paypal.api.merchant_onboarding_status');

    $services->set('sylius_paypal.onboarding.resolver.seller', SellerOnboardingResolver::class)
        ->args([
            service('sylius_paypal.api.onboarding_token'),
            service('sylius_paypal.api.seller_credentials'),
            service('sylius_paypal.api.authorize_client.onboarding'),
            service('sylius_paypal.api.merchant_onboarding_status'),
            service('sylius_paypal.provider.partner_credentials'),
        ]);

    $services->alias(SellerOnboardingResolverInterface::class, 'sylius_paypal.onboarding.resolver.seller');
};
