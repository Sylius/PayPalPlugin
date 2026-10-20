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

use Sylius\Component\Core\Storage\CartStorageInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\PayPalPlugin\Controller\AddToCartAction;
use Sylius\PayPalPlugin\Controller\CancelPayPalOrderAction;
use Sylius\PayPalPlugin\Controller\CancelPayPalPaymentAction;
use Sylius\PayPalPlugin\Controller\CompleteExpressCheckoutAction;
use Sylius\PayPalPlugin\Controller\CompleteOnboardingAction;
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderForPaymentRequestAction;
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromCartAction;
use Sylius\PayPalPlugin\Controller\DownloadPayoutsReportAction;
use Sylius\PayPalPlugin\Controller\EnableSellerAction;
use Sylius\PayPalPlugin\Controller\PayPalButtonsController;
use Sylius\PayPalPlugin\Controller\PayPalPaymentOnErrorAction;
use Sylius\PayPalPlugin\Controller\RefundPayPalLateCaptureAction;
use Sylius\PayPalPlugin\Controller\RefundPayPalPaymentAction;
use Sylius\PayPalPlugin\Controller\ShippingCallbackAction;
use Sylius\PayPalPlugin\Controller\Webhook\PaymentMethodNotifyAction;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_paypal.controller.webhook.payment_method_notify', PaymentMethodNotifyAction::class)
        ->public()
        ->decorate('sylius.controller.payment_method_notify')
        ->args([
            service('.inner'),
            service('monolog.logger.paypal'),
        ]);

    $services->set('sylius_paypal.controller.cancel_paypal_order', CancelPayPalOrderAction::class)
        ->args([
            null,
            null,
            service('request_stack'),
        ]);

    $services->set('sylius_paypal.controller.cancel_paypal_payment', CancelPayPalPaymentAction::class)
        ->args([
            service('sylius_paypal.provider.payment'),
            service('doctrine.orm.entity_manager'),
            service('request_stack'),
            service('sylius_abstraction.state_machine'),
            service('sylius.order_processing.order_payment_processor.checkout'),
            service('sylius_paypal.repository.query.paypal_payment'),
        ]);

    $services->set('sylius_paypal.controller.download_payouts_report', DownloadPayoutsReportAction::class)
        ->args([
            service('sylius_paypal.downloader.report'),
            service('sylius.repository.payment_method'),
        ]);

    $services->set('sylius_paypal.controller.enable_seller', EnableSellerAction::class)
        ->args([
            service('sylius.repository.payment_method'),
            service('sylius_paypal.enabler.payment_method'),
            service('sylius_paypal.provider.onboarding_status_messages'),
        ]);

    $services->set('sylius_paypal.controller.complete_onboarding', CompleteOnboardingAction::class)
        ->args([
            service('sylius_paypal.onboarding.processor.completion'),
            service('sylius_paypal.provider.onboarding_status_messages'),
            service('router'),
            service('monolog.logger.paypal'),
        ]);

    $services->set('sylius_paypal.controller.create_paypal_order_for_payment_request', CreatePayPalOrderForPaymentRequestAction::class)
        ->args([
            service('sylius.repository.payment_request'),
            service('sylius.factory.payment_request'),
            service('sylius.announcer.payment_request'),
            service('sylius_abstraction.state_machine'),
            service('sylius_shop.provider.order_pay.payment_request_pay_url'),
            service('sylius_paypal.checker.payment_source_enabled'),
        ]);

    $services->set('sylius_paypal.controller.create_paypal_order_from_cart', CreatePayPalOrderFromCartAction::class)
        ->args([
            service('sylius.manager.payment'),
            service('sylius_paypal.provider.order'),
            service('sylius_paypal.resolver.capture_payment'),
            service('sylius.remover.payment.order'),
            service('sylius.order_processing.order_processor'),
            service('sylius_paypal.resolver.paypal_payment_methods'),
            service('sylius_paypal.verifier.order_ownership'),
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
            service('sylius_paypal.provider.vault_request'),
        ]);

    $services->set('sylius_paypal.controller.paypal_buttons', PayPalButtonsController::class)
        ->args([
            service('twig'),
            service('router'),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            service('sylius_paypal.provider.paypal_configuration'),
            service('sylius.repository.order'),
            service('sylius_paypal.provider.available_countries'),
            service('sylius_paypal.processor.locale'),
            service('sylius_paypal.provider.web_sdk_configuration'),
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
            service('sylius_paypal.provider.current_paypal_locale'),
            service('sylius_paypal.provider.vault_request'),
        ]);

    $services->set('sylius_paypal.controller.complete_express_checkout', CompleteExpressCheckoutAction::class)
        ->args([
            service('sylius_paypal.provider.order'),
            service('sylius_paypal.verifier.order_ownership'),
            service('sylius_paypal.completer.express_checkout'),
            service('sylius_paypal.dispatcher.approved_paypal_order_payment_request'),
            service('sylius_paypal.provider.payment_request_return_url'),
            service('router'),
            service('monolog.logger.paypal'),
        ]);

    $services->set('sylius_paypal.controller.shipping_callback', ShippingCallbackAction::class)
        ->args([
            service('sylius_paypal.api.callback_signature_verifier'),
            service('sylius_paypal.repository.query.paypal_payment'),
            service('sylius_paypal.provider.available_countries'),
            service('sylius_paypal.factory.paypal_shipping_address'),
            service('sylius_paypal.resolver.shipping_options'),
            service('sylius_paypal.factory.shipping_callback_response'),
            service('sylius_paypal.provider.shipping_callback_amount'),
        ]);

    $services->set('sylius_paypal.controller.paypal_payment_on_error', PayPalPaymentOnErrorAction::class)
        ->args([
            service('request_stack'),
            service('monolog.logger.paypal'),
            service('sylius_paypal.repository.query.paypal_payment'),
            service('sylius_abstraction.state_machine'),
            service('sylius.order_processing.order_payment_processor.checkout'),
            service('doctrine.orm.entity_manager'),
            service('sylius_paypal.checker.payer_action'),
        ]);

    $services->set('sylius_paypal.controller.add_to_cart', AddToCartAction::class)
        ->args([
            service('sylius.factory.add_to_cart_command'),
            service('sylius.context.cart.new'),
            service('doctrine.orm.entity_manager'),
            service('sylius.factory.order_item'),
            service('form.factory'),
            inline_service(MetadataInterface::class)
                ->args(['sylius.order_item'])
                ->factory([service('sylius.resource_registry'), 'get']),
            service('sylius.resource_controller.new_resource_factory'),
            service('sylius.modifier.order_item_quantity'),
            service('sylius.modifier.order'),
            service('sylius.resource_controller.request_configuration_factory'),
            service('router'),
            service(CartStorageInterface::class),
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
        ]);

    $services->set('sylius_paypal.controller.refund_late_capture', RefundPayPalLateCaptureAction::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius_paypal.dispatcher.refund_paypal_late_capture_payment_request'),
            service('security.csrf.token_manager'),
            service('router'),
        ]);

    $services->set('sylius_paypal.controller.refund_payment', RefundPayPalPaymentAction::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius_paypal.dispatcher.refund_paypal_payment_request'),
            service('security.csrf.token_manager'),
            service('router'),
        ]);
};
