<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\ORM\EntityRepository;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApi;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApi;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApi;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApi;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Api\GenericApi;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Api\IdentityApi;
use Sylius\PayPalPlugin\Api\IdentityApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApi;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApi;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApi;
use Sylius\PayPalPlugin\Api\UpdateOrderApi;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Api\WebhookSignatureVerifier;
use Sylius\PayPalPlugin\Api\WebhookSignatureVerifierInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;
use Sylius\PayPalPlugin\Provider\PersistingWebhookIdProvider;
use Sylius\PayPalPlugin\Provider\WebhookIdProvider;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $parameters->set('sylius_paypal.request_trials_limit', 5);
    $parameters->set('sylius_paypal.webhook_base_url', '');
    $parameters->set('sylius_paypal.webhook_id_refresh_cooldown', 300);

    $services->set('sylius_paypal.client.paypal', \Sylius\PayPalPlugin\Client\PayPalClient::class)
        ->args([
            service('sylius.http_client'),
            service('monolog.logger.paypal'),
            service('sylius_paypal.provider.uuid'),
            service('sylius_paypal.provider.paypal_configuration'),
            service('sylius.context.channel'),
            '%sylius_paypal.api_base_url%',
            '%sylius_paypal.request_trials_limit%',
            service(RequestFactoryInterface::class),
            service(StreamFactoryInterface::class),
            '%sylius_paypal.logging.increased%',
        ]);

    $services->alias(PayPalClientInterface::class, 'sylius_paypal.client.paypal');

    $services->set('sylius_paypal.api.authorize_client', AuthorizeClientApi::class)
        ->args([service('sylius_paypal.client.paypal')]);

    $services->alias(AuthorizeClientApiInterface::class, 'sylius_paypal.api.authorize_client');

    $services->set('sylius_paypal.repository.paypal_credentials', EntityRepository::class)
        ->args([PayPalCredentials::class])
        ->factory([service('doctrine.orm.entity_manager'), 'getRepository']);

    $services->set('sylius_paypal.api.cache_authorize_client', CacheAuthorizeClientApi::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('sylius_paypal.repository.paypal_credentials'),
            service('sylius_paypal.api.authorize_client'),
            service('sylius_paypal.provider.uuid'),
        ]);

    $services->alias(CacheAuthorizeClientApiInterface::class, 'sylius_paypal.api.cache_authorize_client');

    $services->set('sylius_paypal.api.complete_order', CompleteOrderApi::class)
        ->args([service('sylius_paypal.client.paypal')]);

    $services->alias(CompleteOrderApiInterface::class, 'sylius_paypal.api.complete_order');

    $services->set('sylius_paypal.api.generic', GenericApi::class)
        ->args([
            service('sylius.http_client'),
            service(RequestFactoryInterface::class),
        ]);

    $services->alias(GenericApiInterface::class, 'sylius_paypal.api.generic');

    $services->set('sylius_paypal.api.create_order', CreateOrderApi::class)
        ->args([
            service('sylius_paypal.client.paypal'),
            service('sylius_paypal.provider.payment_reference_number'),
            service('sylius_paypal.provider.paypal_item_data'),
        ]);

    $services->alias(CreateOrderApiInterface::class, 'sylius_paypal.api.create_order');

    $services->set('sylius_paypal.api.identity', IdentityApi::class)
        ->args([service('sylius_paypal.client.paypal')]);

    $services->alias(IdentityApiInterface::class, 'sylius_paypal.api.identity');

    $services->set('sylius_paypal.api.order_details', OrderDetailsApi::class)
        ->args([service('sylius_paypal.client.paypal')]);

    $services->alias(OrderDetailsApiInterface::class, 'sylius_paypal.api.order_details');

    $services->set('sylius_paypal.api.webhook', WebhookApi::class)
        ->args([
            service('sylius.http_client'),
            '%sylius_paypal.api_base_url%',
            service(RequestFactoryInterface::class),
            service(StreamFactoryInterface::class),
        ]);

    $services->alias(WebhookApiInterface::class, 'sylius_paypal.api.webhook');

    $services->set('sylius_paypal.api.webhook_signature_verifier', WebhookSignatureVerifier::class)
        ->args([
            service('sylius.http_client'),
            '%sylius_paypal.api_base_url%',
            service(RequestFactoryInterface::class),
            service(StreamFactoryInterface::class),
        ]);

    $services->alias(WebhookSignatureVerifierInterface::class, 'sylius_paypal.api.webhook_signature_verifier');

    $services->set('sylius_paypal.provider.webhook_id', WebhookIdProvider::class)
        ->args([
            service('sylius_paypal.api.generic'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('router'),
            '%sylius_paypal.api_base_url%',
            '%sylius_paypal.webhook_base_url%',
        ]);

    $services->set('sylius_paypal.provider.webhook_id.persisting', PersistingWebhookIdProvider::class)
        ->decorate('sylius_paypal.provider.webhook_id')
        ->args([
            service('.inner'),
            service('doctrine.orm.entity_manager'),
            service('cache.app'),
            '%sylius_paypal.webhook_id_refresh_cooldown%',
        ]);

    $services->alias(WebhookIdProviderInterface::class, 'sylius_paypal.provider.webhook_id');

    $services->set('sylius_paypal.api.update_order', UpdateOrderApi::class)
        ->args([
            service('sylius_paypal.client.paypal'),
            service('sylius_paypal.provider.payment_reference_number'),
            service('sylius_paypal.provider.paypal_item_data'),
        ]);

    $services->alias(UpdateOrderApiInterface::class, 'sylius_paypal.api.update_order');

    $services->set('sylius_paypal.api.update_order_address', UpdateOrderAddressApi::class)
        ->args([service('sylius_paypal.client.paypal')]);

    $services->set('sylius_paypal.api.refund_payment', RefundPaymentApi::class)
        ->args([service('sylius_paypal.client.paypal')]);

    $services->alias(RefundPaymentApiInterface::class, 'sylius_paypal.api.refund_payment');
};
