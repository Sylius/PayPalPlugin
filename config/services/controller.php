<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\Component\Core\Storage\CartStorageInterface;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\PayPalPlugin\Controller\AddToCartAction;
use Sylius\PayPalPlugin\Controller\CancelLastPayPalPaymentAction;
use Sylius\PayPalPlugin\Controller\CancelPayPalCheckoutPaymentAction;
use Sylius\PayPalPlugin\Controller\CancelPayPalOrderAction;
use Sylius\PayPalPlugin\Controller\CancelPayPalPaymentAction;
use Sylius\PayPalPlugin\Controller\CompletePayPalOrderAction;
use Sylius\PayPalPlugin\Controller\CompletePayPalOrderFromPaymentPageAction;
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderAction;
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromCartAction;
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromPaymentPageAction;
use Sylius\PayPalPlugin\Controller\DownloadPayoutsReportAction;
use Sylius\PayPalPlugin\Controller\EnableSellerAction;
use Sylius\PayPalPlugin\Controller\PayPalButtonsController;
use Sylius\PayPalPlugin\Controller\PayPalPaymentOnErrorAction;
use Sylius\PayPalPlugin\Controller\PayWithPayPalFormAction;
use Sylius\PayPalPlugin\Controller\ProcessPayPalOrderAction;
use Sylius\PayPalPlugin\Controller\RedirectCancelAction;
use Sylius\PayPalPlugin\Controller\RedirectReturnAction;
use Sylius\PayPalPlugin\Controller\RefundPayPalLateCaptureAction;
use Sylius\PayPalPlugin\Controller\ShippingCallbackAction;
use Sylius\PayPalPlugin\Controller\UpdatePayPalOrderAction;
use Sylius\PayPalPlugin\Controller\Webhook\PayPalWebhookAction;
use Sylius\PayPalPlugin\Controller\Webhook\RefundOrderAction;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_paypal.controller.webhook.paypal_webhook', PayPalWebhookAction::class)
        ->args([
            service('sylius_paypal.verifier.webhook_request'),
            [
                service('sylius_paypal.processor.webhook.refund_order'),
                service('sylius_paypal.processor.webhook.capture_payment'),
            ],
            service('monolog.logger.paypal'),
        ]);

    $services->set('sylius_paypal.controller.webhook.refund_order', RefundOrderAction::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius_paypal.provider.payment'),
            service('sylius.manager.payment'),
            service('sylius_paypal.provider.paypal_refund_data'),
            service('sylius_paypal.provider.paypal_payment_method'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.webhook_signature_verifier'),
            service('sylius_paypal.provider.webhook_id'),
            service('sylius_paypal.repository.query.paypal_payment'),
            service('sylius_paypal.verifier.webhook_request'),
        ])
        ->deprecate('sylius/paypal-plugin', '2.1', 'The "%service_id%" service is deprecated, use "sylius_paypal.controller.webhook.paypal_webhook" instead.');

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

    $services->set('sylius_paypal.controller.cancel_last_paypal_payment', CancelLastPayPalPaymentAction::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('sylius_abstraction.state_machine'),
            service('sylius.order_processing.order_payment_processor.checkout'),
            service('sylius.repository.order'),
        ]);

    $services->set('sylius_paypal.controller.cancel_paypal_checkout_payment', CancelPayPalCheckoutPaymentAction::class)
        ->args([
            service('sylius_paypal.provider.payment'),
            service('sylius_paypal.manager.payment_state'),
            service('sylius_paypal.repository.query.paypal_payment'),
        ]);

    $services->set('sylius_paypal.controller.complete_paypal_order', CompletePayPalOrderAction::class)
        ->args([
            service('sylius_paypal.manager.payment_state'),
            service('router'),
            service('sylius_paypal.provider.order'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.order_details'),
            service('sylius_paypal.verifier.three_d_secure'),
        ]);

    $services->set('sylius_paypal.controller.create_paypal_order_from_payment_page', CreatePayPalOrderFromPaymentPageAction::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius_paypal.manager.payment_state'),
            service('sylius_paypal.provider.order'),
            service('sylius_paypal.resolver.capture_payment'),
            service('sylius.order_processing.order_payment_processor.checkout'),
            service('doctrine.orm.entity_manager'),
            service('sylius_paypal.verifier.order_ownership'),
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
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
        ]);

    $services->set('sylius_paypal.controller.create_paypal_order', CreatePayPalOrderAction::class)
        ->args([
            service('sylius_paypal.manager.payment_state'),
            service('sylius_paypal.provider.order'),
            service('sylius_paypal.resolver.capture_payment'),
            service('sylius_paypal.provider.paypal_payment_source'),
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
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
        ]);

    $services->set('sylius_paypal.controller.pay_with_paypal_form', PayWithPayPalFormAction::class)
        ->args([
            service('twig'),
            service('sylius.repository.payment'),
            null,
            null,
            null,
            null,
            null,
            service('sylius_paypal.provider.paypal_payment_page_context'),
            service('router'),
            service('sylius_paypal.checker.payer_action'),
            '%sylius_paypal.web_url%',
        ]);

    $services->set('sylius_paypal.controller.process_paypal_order', ProcessPayPalOrderAction::class)
        ->args([
            service('sylius.repository.customer'),
            service('sylius.factory.customer'),
            service('sylius.factory.address'),
            service('sylius.manager.order'),
            service('sylius_abstraction.state_machine'),
            service('sylius_paypal.manager.payment_state'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.order_details'),
            service('sylius_paypal.provider.order'),
            service('sylius_paypal.verifier.payment_amount'),
            service('router'),
            service('sylius_paypal.completer.express_order'),
            service('sylius.order_processing.order_processor'),
            service('sylius.repository.shipping_method'),
            service('sylius_paypal.factory.express_order_address'),
            service('sylius_paypal.verifier.order_ownership'),
            service('sylius_paypal.api.update_order'),
        ]);

    $services->set('sylius_paypal.controller.update_paypal_order', UpdatePayPalOrderAction::class)
        ->args([
            service('sylius_paypal.provider.payment'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.update_order'),
            service('sylius.factory.address'),
            service('sylius.order_processing.order_processor'),
            service('sylius_paypal.repository.query.paypal_payment'),
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

    $services->set('sylius_paypal.controller.complete_paypal_order_from_payment_page', CompletePayPalOrderFromPaymentPageAction::class)
        ->args([
            service('sylius_paypal.manager.payment_state'),
            service('router'),
            service('sylius_paypal.provider.order'),
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.order'),
            service('sylius_paypal.verifier.payment_amount'),
            service('sylius.order_processing.order_processor'),
            service('sylius_paypal.verifier.order_ownership'),
        ]);

    $services->set('sylius_paypal.controller.redirect_return', RedirectReturnAction::class)
        ->args([
            service('sylius_paypal.provider.order'),
            service('sylius_paypal.processor.payment_settlement'),
            service('sylius_paypal.checker.payer_action'),
            service('router'),
            service('request_stack'),
        ]);

    $services->set('sylius_paypal.controller.redirect_cancel', RedirectCancelAction::class)
        ->args([
            service('sylius_paypal.provider.order'),
            service('sylius_paypal.processor.payment_settlement'),
            service('sylius_paypal.checker.payer_action'),
            service('sylius_abstraction.state_machine'),
            service('sylius.order_processing.order_payment_processor.after_checkout'),
            service('doctrine.orm.entity_manager'),
            service('router'),
            service('request_stack'),
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
            service('sylius_paypal.processor.late_capture_refund'),
            service('security.csrf.token_manager'),
            service('router'),
        ]);
};
