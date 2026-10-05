<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use phpseclib3\Net\SFTP;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\PayPalPlugin\ApiPlatform\PayPalPayment;
use Sylius\PayPalPlugin\Checker\PayerActionChecker;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Completer\PayPalExpressOrderCompleter;
use Sylius\PayPalPlugin\Completer\PayPalExpressOrderCompleterInterface;
use Sylius\PayPalPlugin\Console\Command\CompletePaidPaymentsCommand;
use Sylius\PayPalPlugin\Console\Command\RegisterWebhookEventTypesCommand;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreator;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Creator\PayPalSandboxPaymentMethodCreator;
use Sylius\PayPalPlugin\Downloader\ReportDownloaderInterface;
use Sylius\PayPalPlugin\Downloader\SftpPayoutsReportDownloader;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Enabler\PayPalPaymentMethodEnabler;
use Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactory;
use Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalItemFactory;
use Sylius\PayPalPlugin\Factory\PayPalItemFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactory;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactory;
use Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactoryInterface;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactory;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Factory\ShippingCallbackResponseFactory;
use Sylius\PayPalPlugin\Factory\ShippingCallbackResponseFactoryInterface;
use Sylius\PayPalPlugin\Factory\ShippingOptionsFactory;
use Sylius\PayPalPlugin\Factory\ShippingOptionsFactoryInterface;
use Sylius\PayPalPlugin\Form\Extension\PaymentMethodTypeExtension;
use Sylius\PayPalPlugin\Form\Type\PayPalConfigurationType;
use Sylius\PayPalPlugin\Form\Type\PayPalSandboxCredentialsType;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGenerator;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Listener\PayPalPaymentMethodListener;
use Sylius\PayPalPlugin\Manager\PaymentStateManager;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Processor\AfterCheckoutOrderPaymentProcessor;
use Sylius\PayPalPlugin\Processor\LocaleProcessor;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Processor\OrderPaymentProcessor;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalAddressProcessor;
use Sylius\PayPalPlugin\Processor\PayPalOrderCompleteProcessor;
use Sylius\PayPalPlugin\Processor\PayPalPaymentCompleteProcessor;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundProcessor;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;
use Sylius\PayPalPlugin\Processor\UiPayPalPaymentRefundProcessor;
use Sylius\PayPalPlugin\Processor\Webhook\CapturePaymentWebhookProcessor;
use Sylius\PayPalPlugin\Processor\Webhook\RefundOrderWebhookProcessor;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProvider;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\ChannelAvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProvider;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProviderInterface;
use Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProvider;
use Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProviderInterface;
use Sylius\PayPalPlugin\Provider\ExperienceContextProvider;
use Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface;
use Sylius\PayPalPlugin\Provider\NonceProvider;
use Sylius\PayPalPlugin\Provider\NonceProviderInterface;
use Sylius\PayPalPlugin\Provider\OrderItemNonNeutralTaxesProvider;
use Sylius\PayPalPlugin\Provider\OrderItemNonNeutralTaxProviderInterface;
use Sylius\PayPalPlugin\Provider\OrderProvider;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Provider\PaymentProvider;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProvider;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProvider;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProvider;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProvider;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProvider;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProvider;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\ShippingCallbackAmountProvider;
use Sylius\PayPalPlugin\Provider\ShippingCallbackAmountProviderInterface;
use Sylius\PayPalPlugin\Provider\ShippingCallbackUrlProvider;
use Sylius\PayPalPlugin\Provider\ShippingCallbackUrlProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProvider;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProvider;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Sylius\PayPalPlugin\Provider\WebSdkConfigurationProvider;
use Sylius\PayPalPlugin\Provider\WebSdkConfigurationProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrar;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrarInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrar;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQuery;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolver;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Sylius\PayPalPlugin\Resolver\PayPalDefaultPaymentMethodResolver;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolver;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPrioritisingPaymentMethodsResolver;
use Sylius\PayPalPlugin\Resolver\ShippingOptionsResolver;
use Sylius\PayPalPlugin\Resolver\ShippingOptionsResolverInterface;
use Sylius\PayPalPlugin\Resolver\SupportedLocaleResolver;
use Sylius\PayPalPlugin\Resolver\SupportedLocaleResolverInterface;
use Sylius\PayPalPlugin\Twig\Component\PayPalOnboardingModalComponent;
use Sylius\PayPalPlugin\Twig\Component\PayPalSandboxModalComponent;
use Sylius\PayPalPlugin\Twig\OrderAddressExtension;
use Sylius\PayPalPlugin\Twig\PayPalExtension;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;
use Sylius\PayPalPlugin\Updater\PayPalPaymentUpdater;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifier;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifier;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifier;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifier;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifierInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $container->import('services/**/*.php');

    $parameters->set('sylius_paypal.prioritize_paypal_as_default_method', true);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.updatable_states', [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW]);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.cancellable_states', [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED]);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.refundable_states', [PaymentInterface::STATE_COMPLETED]);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.settleable_states', [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_CANCELLED, PaymentInterface::STATE_FAILED]);

    $services->set('sylius_paypal.form.extension.payment_method', PaymentMethodTypeExtension::class)
        ->tag('form.type_extension');

    $services->set('sylius_paypal.form.type.paypal_configuration', PayPalConfigurationType::class)
        ->args(['%sylius_paypal.sandbox%'])
        ->tag('form.type')
        ->tag('sylius.gateway_configuration_type', ['type' => 'sylius_paypal', 'label' => 'sylius_paypal.label']);

    $services->set('sylius_paypal.form.type.paypal_sandbox_credentials', PayPalSandboxCredentialsType::class)
        ->tag('form.type');

    $services->set('sylius_paypal.generator.paypal_auth_assertion', PayPalAuthAssertionGenerator::class);

    $services->alias(PayPalAuthAssertionGeneratorInterface::class, 'sylius_paypal.generator.paypal_auth_assertion');

    $services->set('sylius_paypal.api_platform.paypal_payment', PayPalPayment::class)
        ->args([
            service('router'),
            service('sylius_paypal.provider.available_countries'),
            service('sylius_paypal.provider.paypal_configuration'),
        ])
        ->tag('sylius.api.payment_method_handler');

    $services->set('sylius_paypal.listener.paypal_payment_method', PayPalPaymentMethodListener::class)
        ->args([
            service('router'),
            service('request_stack'),
            service('sylius_paypal.provider.paypal_payment_method'),
        ])
        ->tag('kernel.event_listener', ['event' => 'sylius.payment_method.initialize_create', 'method' => 'initializeCreate']);

    $services->set('sylius_paypal.manager.payment_state', PaymentStateManager::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.payment'),
            service('sylius_paypal.processor.payment_complete'),
            service('sylius_paypal.checker.payer_action'),
        ]);

    $services->alias(PaymentStateManagerInterface::class, 'sylius_paypal.manager.payment_state');

    $services->set('sylius_paypal.completer.express_order', PayPalExpressOrderCompleter::class)
        ->args([
            service('sylius_paypal.manager.payment_state'),
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.order'),
        ]);

    $services->alias(PayPalExpressOrderCompleterInterface::class, 'sylius_paypal.completer.express_order');

    $services->set('sylius_paypal.factory.paypal_shipping_address', PayPalShippingAddressFactory::class)
        ->args([
            service('sylius.factory.address'),
            service('sylius.repository.province'),
        ]);

    $services->alias(PayPalShippingAddressFactoryInterface::class, 'sylius_paypal.factory.paypal_shipping_address');

    $services->set('sylius_paypal.factory.express_order_address', ExpressOrderAddressFactory::class)
        ->args([
            service('sylius.factory.address'),
            service('sylius_paypal.factory.paypal_shipping_address'),
        ]);

    $services->alias(ExpressOrderAddressFactoryInterface::class, 'sylius_paypal.factory.express_order_address');

    $services->set('sylius_paypal.factory.purchase_unit', PurchaseUnitFactory::class)
        ->args([
            service('sylius_paypal.provider.payment_reference_number'),
            service('sylius_paypal.provider.paypal_item_data'),
        ]);

    $services->alias(PurchaseUnitFactoryInterface::class, 'sylius_paypal.factory.purchase_unit');

    $services->set('sylius_paypal.factory.paypal_order', PayPalOrderFactory::class)
        ->args([
            service('sylius_paypal.factory.purchase_unit'),
            service('router'),
            service('sylius_paypal.provider.shipping_callback_url'),
            service('sylius_paypal.provider.experience_context'),
            service('sylius_paypal.provider.paypal_payment_source'),
        ]);

    $services->alias(PayPalOrderFactoryInterface::class, 'sylius_paypal.factory.paypal_order');

    $services->set('sylius_paypal.provider.experience_context', ExperienceContextProvider::class)
        ->args([service('sylius_paypal.provider.current_paypal_locale')]);

    $services->alias(ExperienceContextProviderInterface::class, 'sylius_paypal.provider.experience_context');

    $services->set('sylius_paypal.provider.paypal_payment_source', PayPalPaymentSourceProvider::class);

    $services->alias(PayPalPaymentSourceProviderInterface::class, 'sylius_paypal.provider.paypal_payment_source');

    $services->set('sylius_paypal.factory.shipping_callback_response', ShippingCallbackResponseFactory::class);

    $services->alias(ShippingCallbackResponseFactoryInterface::class, 'sylius_paypal.factory.shipping_callback_response');

    $services->set('sylius_paypal.factory.shipping_options', ShippingOptionsFactory::class);

    $services->alias(ShippingOptionsFactoryInterface::class, 'sylius_paypal.factory.shipping_options');

    $services->set('sylius_paypal.provider.order', OrderProvider::class)
        ->args([service('sylius.repository.order')]);

    $services->alias(OrderProviderInterface::class, 'sylius_paypal.provider.order');

    $services->set('sylius_paypal.provider.shipping_callback_url', ShippingCallbackUrlProvider::class)
        ->args([service('router')]);

    $services->alias(ShippingCallbackUrlProviderInterface::class, 'sylius_paypal.provider.shipping_callback_url');

    $services->set('sylius_paypal.provider.paypal_order_created_statuses', PayPalOrderCreatedStatusesProvider::class);

    $services->alias(PayPalOrderCreatedStatusesProviderInterface::class, 'sylius_paypal.provider.paypal_order_created_statuses');

    $services->set('sylius_paypal.provider.payment', PaymentProvider::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius.repository.order'),
        ])
        ->deprecate('sylius/paypal-plugin', '1.7', 'The "%service_id%" service is deprecated since 1.7 and will be removed in 3.0.');

    $services->alias(PaymentProviderInterface::class, 'sylius_paypal.provider.payment');

    $services->set('sylius_paypal.provider.order_item_non_neutral_tax', OrderItemNonNeutralTaxesProvider::class);

    $services->alias(OrderItemNonNeutralTaxProviderInterface::class, 'sylius_paypal.provider.order_item_non_neutral_tax');

    $services->set('sylius_paypal.provider.paypal_item_data', PayPalItemDataProvider::class)
        ->args([
            service('sylius_paypal.provider.order_item_non_neutral_tax'),
            service('sylius_paypal.factory.paypal_item'),
        ]);

    $services->alias(PayPalItemDataProviderInterface::class, 'sylius_paypal.provider.paypal_item_data');

    $services->set('sylius_paypal.factory.paypal_item', PayPalItemFactory::class)
        ->args([service('router')]);

    $services->alias(PayPalItemFactoryInterface::class, 'sylius_paypal.factory.paypal_item');

    $services->set('sylius_paypal.provider.paypal_payment_method', PayPalPaymentMethodProvider::class)
        ->args([service('sylius.repository.payment_method')]);

    $services->alias(PayPalPaymentMethodProviderInterface::class, 'sylius_paypal.provider.paypal_payment_method');

    $services->set('sylius_paypal.provider.paypal_refund_data', PayPalRefundDataProvider::class)
        ->args([
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.generic'),
            service('sylius_paypal.provider.paypal_payment_method'),
        ]);

    $services->alias(PayPalRefundDataProviderInterface::class, 'sylius_paypal.provider.paypal_refund_data');

    $services->set('sylius_paypal.provider.available_countries', AvailableCountriesProvider::class)
        ->args([
            service('sylius.repository.country'),
            service('sylius.context.channel'),
        ]);

    $services->alias(AvailableCountriesProviderInterface::class, 'sylius_paypal.provider.available_countries');

    $services->alias(ChannelAvailableCountriesProviderInterface::class, 'sylius_paypal.provider.available_countries');

    $services->set('sylius_paypal.resolver.capture_payment', CapturePaymentResolver::class)
        ->args([service('payum')]);

    $services->alias(CapturePaymentResolverInterface::class, 'sylius_paypal.resolver.capture_payment');

    $services->set('sylius_paypal.order_processing.order_payment_processor.after_checkout', AfterCheckoutOrderPaymentProcessor::class)
        ->decorate('sylius.order_processing.order_payment_processor.after_checkout')
        ->args([service('.inner')]);

    $services->set('sylius_paypal.order_processing.order_payment_processor.checkout', OrderPaymentProcessor::class)
        ->decorate('sylius.order_processing.order_payment_processor.checkout')
        ->args([
            service('.inner'),
            service('sylius_abstraction.state_machine'),
        ]);

    $services->set('sylius_paypal.processor.paypal_order_complete', PayPalOrderCompleteProcessor::class)
        ->public()
        ->args([
            service('sylius_paypal.manager.payment_state'),
            service('sylius_paypal.verifier.payment_amount'),
        ]);

    $services->set('sylius_paypal.processor.payment_complete', PayPalPaymentCompleteProcessor::class)
        ->args([service('payum')]);

    $services->alias(PaymentCompleteProcessorInterface::class, 'sylius_paypal.processor.payment_complete');

    $services->set('sylius_paypal.processor.locale', LocaleProcessor::class)
        ->args([service('sylius_paypal.resolver.supported_locale')]);

    $services->alias(LocaleProcessorInterface::class, 'sylius_paypal.processor.locale');

    $services->set('sylius_paypal.provider.current_paypal_locale', CurrentPayPalLocaleProvider::class)
        ->args([
            service('sylius.context.locale'),
            service('sylius_paypal.processor.locale'),
        ]);

    $services->alias(CurrentPayPalLocaleProviderInterface::class, 'sylius_paypal.provider.current_paypal_locale');

    $services->set('sylius_paypal.processor.payment_refund', PayPalPaymentRefundProcessor::class)
        ->public()
        ->args([
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.order_details'),
            service('sylius_paypal.api.refund_payment'),
            service('sylius_paypal.generator.paypal_auth_assertion'),
            service('sylius_paypal.provider.refund_reference_number'),
        ]);

    $services->alias(PaymentRefundProcessorInterface::class, 'sylius_paypal.processor.payment_refund');

    $services->set('sylius_paypal.processor.ui_paypal_payment_refund', UiPayPalPaymentRefundProcessor::class)
        ->decorate('sylius_paypal.processor.payment_refund')
        ->args([service('.inner')]);

    $services->set('sylius_paypal.resolver.payment_method.paypal', PayPalDefaultPaymentMethodResolver::class)
        ->decorate('sylius.resolver.payment_method.default')
        ->args([
            service('.inner'),
            service('sylius.repository.payment_method'),
            '%sylius_paypal.prioritize_paypal_as_default_method%',
        ]);

    $services->set('sylius_paypal.resolver.payment_method.paypal_prioritising', PayPalPrioritisingPaymentMethodsResolver::class)
        ->decorate('sylius.resolver.payment_methods')
        ->args([
            service('.inner'),
            '%sylius_paypal.prioritized_factory_name%',
        ]);

    $services->set('sylius_paypal.provider.paypal_configuration', PayPalConfigurationProvider::class)
        ->args([service('sylius.repository.payment_method')]);

    $services->alias(PayPalConfigurationProviderInterface::class, 'sylius_paypal.provider.paypal_configuration');

    $services->alias(PayPalFundingSourcesConfigurationProviderInterface::class, 'sylius_paypal.provider.paypal_configuration');

    $services->set('sylius_paypal.provider.web_sdk_configuration', WebSdkConfigurationProvider::class)
        ->args([
            service('sylius_paypal.provider.paypal_configuration'),
            '%sylius_paypal.web_url%',
            '%sylius_paypal.sandbox%',
            '%sylius_paypal.test_buyer_country%',
        ]);

    $services->alias(WebSdkConfigurationProviderInterface::class, 'sylius_paypal.provider.web_sdk_configuration');

    $services->set('sylius_paypal.provider.paypal_payment_page_context', PayPalPaymentPageContextProvider::class)
        ->args([
            service('sylius_paypal.provider.web_sdk_configuration'),
            service('router'),
            service('sylius_paypal.processor.locale'),
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
            service('sylius_paypal.provider.eligible_redirect_payment_sources'),
        ]);

    $services->alias(PayPalPaymentPageContextProviderInterface::class, 'sylius_paypal.provider.paypal_payment_page_context');

    $services->set('sylius_paypal.provider.uuid', UuidProvider::class);

    $services->alias(UuidProviderInterface::class, 'sylius_paypal.provider.uuid');

    $services->set('sylius_paypal.provider.nonce', NonceProvider::class);

    $services->alias(NonceProviderInterface::class, 'sylius_paypal.provider.nonce');

    $services->set('sylius_paypal.client.sftp', SFTP::class)
        ->args(['%sylius_paypal.reports_sftp_host%']);

    $services->set('sylius_paypal.downloader.report', SftpPayoutsReportDownloader::class)
        ->args([service('sylius_paypal.client.sftp')]);

    $services->alias(ReportDownloaderInterface::class, 'sylius_paypal.downloader.report');

    $services->set('sylius_paypal.processor.paypal_address', PayPalAddressProcessor::class)
        ->args([service('doctrine.orm.entity_manager')])
        ->deprecate('sylius/paypal-plugin', '1.7', 'The "%service_id%" service is deprecated since 1.7 and will be removed in 3.0.');

    $services->set('sylius_paypal.updater.payment', PayPalPaymentUpdater::class)
        ->args([service('sylius.manager.payment')]);

    $services->alias(PaymentUpdaterInterface::class, 'sylius_paypal.updater.payment');

    $services->set('sylius_paypal.enabler.payment_method', PayPalPaymentMethodEnabler::class)
        ->args([
            service('sylius_paypal.api.authorize_client'),
            service('sylius_paypal.api.merchant_onboarding_status'),
            service('sylius.manager.payment_method'),
            service('sylius_paypal.registrar.seller_webhook'),
            service('sylius_paypal.provider.partner_credentials'),
        ]);

    $services->alias(PaymentMethodEnablerInterface::class, 'sylius_paypal.enabler.payment_method');

    $services->set('sylius_paypal.provider.payment_reference_number', PaymentReferenceNumberProvider::class);

    $services->alias(PaymentReferenceNumberProviderInterface::class, 'sylius_paypal.provider.payment_reference_number');

    $services->set('sylius_paypal.provider.eligible_redirect_payment_sources', EligibleRedirectPaymentSourcesProvider::class)
        ->args([
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.find_eligible_methods'),
            service('sylius_paypal.provider.paypal_configuration'),
            service('monolog.logger.paypal'),
        ]);

    $services->alias(EligibleRedirectPaymentSourcesProviderInterface::class, 'sylius_paypal.provider.eligible_redirect_payment_sources');

    $services->set('sylius_paypal.processor.webhook.refund_order', RefundOrderWebhookProcessor::class)
        ->args([
            service('sylius_paypal.provider.paypal_refund_data'),
            service('sylius_paypal.repository.query.paypal_payment'),
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.payment'),
        ]);

    $services->set('sylius_paypal.processor.webhook.capture_payment', CapturePaymentWebhookProcessor::class)
        ->args([
            service('sylius_paypal.repository.query.paypal_payment'),
            service('sylius_paypal.processor.payment_settlement'),
            service('monolog.logger.paypal'),
        ]);

    $services->set('sylius_paypal.verifier.webhook_request', WebhookRequestVerifier::class)
        ->args([
            service('sylius_paypal.provider.paypal_payment_method'),
            service('sylius_paypal.provider.webhook_id'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.webhook_signature_verifier'),
        ]);

    $services->alias(WebhookRequestVerifierInterface::class, 'sylius_paypal.verifier.webhook_request');

    $services->set('sylius_paypal.checker.payer_action', PayerActionChecker::class);

    $services->alias(PayerActionCheckerInterface::class, 'sylius_paypal.checker.payer_action');

    $services->set('sylius_paypal.processor.payment_settlement', PayPalPaymentSettlementProcessor::class)
        ->args([
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.order_details'),
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.payment'),
            service('monolog.logger.paypal'),
        ]);

    $services->alias(PaymentSettlementProcessorInterface::class, 'sylius_paypal.processor.payment_settlement');

    $services->set('sylius_paypal.provider.webhook_url', WebhookUrlProvider::class)
        ->args([
            service('router'),
            '%sylius_paypal.webhook_base_url%',
        ]);

    $services->alias(WebhookUrlProviderInterface::class, 'sylius_paypal.provider.webhook_url');

    $services->set('sylius_paypal.registrar.seller_webhook_event_types', SellerWebhookEventTypesRegistrar::class)
        ->args([
            service('sylius_paypal.provider.webhook_id'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.update_webhook'),
            service('sylius_paypal.api.webhook'),
            service('sylius_paypal.provider.webhook_url'),
        ]);

    $services->alias(SellerWebhookEventTypesRegistrarInterface::class, 'sylius_paypal.registrar.seller_webhook_event_types');

    $services->set('sylius_paypal.console.command.register_webhook_event_types', RegisterWebhookEventTypesCommand::class)
        ->args([
            service('sylius.repository.payment_method'),
            service('sylius_paypal.registrar.seller_webhook_event_types'),
        ])
        ->tag('console.command');

    $services->set('sylius_paypal.console.command.complete_paid_payments', CompletePaidPaymentsCommand::class)
        ->args([
            service('sylius.repository.payment'),
            null,
            null,
            null,
            null,
            service('sylius_paypal.processor.payment_settlement'),
        ])
        ->tag('console.command');

    $services->set('sylius_paypal.provider.refund_reference_number', RefundReferenceNumberProvider::class);

    $services->alias(RefundReferenceNumberProviderInterface::class, 'sylius_paypal.provider.refund_reference_number');

    $services->set('sylius_paypal.registrar.seller_webhook', SellerWebhookRegistrar::class)
        ->args([
            service('sylius_paypal.api.authorize_client'),
            service('router'),
            service('sylius_paypal.api.webhook'),
            service('sylius_paypal.provider.webhook_url'),
        ]);

    $services->alias(SellerWebhookRegistrarInterface::class, 'sylius_paypal.registrar.seller_webhook');

    $services->set('sylius_paypal.creator.sandbox_payment_method', PayPalSandboxPaymentMethodCreator::class)
        ->args([
            service('sylius.factory.gateway_config'),
            service('sylius.factory.payment_method'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('sylius_paypal.creator.onboarding_payment_method', PayPalOnboardingPaymentMethodCreator::class)
        ->args([
            service('sylius.factory.gateway_config'),
            service('sylius.factory.payment_method'),
            service('doctrine.orm.entity_manager'),
            '%sylius_paypal.partner_attribution_id%',
        ]);

    $services->alias(PayPalOnboardingPaymentMethodCreatorInterface::class, 'sylius_paypal.creator.onboarding_payment_method');

    $services->set('sylius_paypal.twig.extension.paypal', PayPalExtension::class)
        ->args([
            '%sylius_paypal.sandbox%',
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
            service('sylius.context.channel'),
            service(WebSdkConfigurationProviderInterface::class),
            service('sylius_paypal.checker.payer_action'),
            service('sylius_paypal.provider.current_paypal_locale'),
            '%sylius_paypal.partner_js_url%',
        ])
        ->tag('twig.extension');

    $services->set('sylius_paypal.twig.extension.order_address', OrderAddressExtension::class)
        ->tag('twig.extension');

    $services->set('sylius_paypal.twig.component.paypal_sandbox_modal', PayPalSandboxModalComponent::class)
        ->args([
            service('form.factory'),
            service('sylius_paypal.creator.sandbox_payment_method'),
            service('request_stack'),
            service('logger'),
            service('router'),
        ])
        ->tag('sylius.live_component.admin', ['key' => 'sylius_paypal:sandbox_onboarding_modal', 'template' => '@SyliusPayPalPlugin/admin/shared/components/paypal_sandbox_modal.html.twig']);

    $services->set('sylius_paypal.twig.component.paypal_onboarding_modal', PayPalOnboardingModalComponent::class)
        ->args([
            service('sylius_paypal.provider.onboarding_url'),
            service('sylius_paypal.provider.seller_nonce'),
            service('sylius_paypal.provider.paypal_payment_method'),
            service('monolog.logger.paypal'),
        ])
        ->tag('sylius.live_component.admin', ['key' => 'sylius_paypal:live_onboarding_modal', 'template' => '@SyliusPayPalPlugin/admin/shared/components/paypal_onboarding_modal.html.twig']);

    $services->set('sylius_paypal.verifier.payment_amount', PaymentAmountVerifier::class);

    $services->alias(PaymentAmountVerifierInterface::class, 'sylius_paypal.verifier.payment_amount');

    $services->set('sylius_paypal.verifier.three_d_secure', ThreeDSecureVerifier::class);

    $services->alias(ThreeDSecureVerifierInterface::class, 'sylius_paypal.verifier.three_d_secure');

    $services->set('sylius_paypal.verifier.order_ownership', OrderOwnershipVerifier::class)
        ->args([service('sylius.context.cart')]);

    $services->alias(OrderOwnershipVerifierInterface::class, 'sylius_paypal.verifier.order_ownership');

    $services->set('sylius_paypal.repository.query.paypal_payment', PaypalPaymentQuery::class)
        ->args([
            service('sylius.manager.payment'),
            service('sylius.repository.payment'),
            '%sylius_paypal.repository.query.pay_pal_payment.updatable_states%',
            '%sylius_paypal.repository.query.pay_pal_payment.cancellable_states%',
            '%sylius_paypal.repository.query.pay_pal_payment.refundable_states%',
            '%sylius_paypal.repository.query.pay_pal_payment.settleable_states%',
        ]);

    $services->alias(PaypalPaymentQueryInterface::class, 'sylius_paypal.repository.query.paypal_payment');

    $services->alias(SettleablePaypalPaymentQueryInterface::class, 'sylius_paypal.repository.query.paypal_payment');

    $services->set('sylius_paypal.resolver.paypal_payment_methods', PayPalPaymentMethodsResolver::class)
        ->args([service('sylius.repository.payment_method')]);

    $services->alias(PayPalPaymentMethodsResolverInterface::class, 'sylius_paypal.resolver.paypal_payment_methods');

    $services->set('sylius_paypal.resolver.supported_locale', SupportedLocaleResolver::class)
        ->args(['%sylius_paypal.supported_locales%']);

    $services->alias(SupportedLocaleResolverInterface::class, 'sylius_paypal.resolver.supported_locale');

    $services->set('sylius_paypal.resolver.shipping_options', ShippingOptionsResolver::class)
        ->args([
            service('sylius.resolver.shipping_methods'),
            service('sylius.calculator.shipping'),
            service('sylius_paypal.factory.shipping_options'),
        ]);

    $services->alias(ShippingOptionsResolverInterface::class, 'sylius_paypal.resolver.shipping_options');

    $services->set('sylius_paypal.provider.shipping_callback_amount', ShippingCallbackAmountProvider::class)
        ->args([
            service('sylius.order_processing.order_processor'),
            service('sylius.resolver.shipping_methods'),
            service('sylius_paypal.factory.purchase_unit'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->alias(ShippingCallbackAmountProviderInterface::class, 'sylius_paypal.provider.shipping_callback_amount');
};
