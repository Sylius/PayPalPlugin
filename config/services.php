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

use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\PayPalPlugin\ApiPlatform\PayPalPayment;
use Sylius\PayPalPlugin\Applicator\PaymentCaptureApplicator;
use Sylius\PayPalPlugin\Applicator\PaymentCaptureApplicatorInterface;
use Sylius\PayPalPlugin\Applicator\PaymentRefundApplicator;
use Sylius\PayPalPlugin\Applicator\PaymentRefundApplicatorInterface;
use Sylius\PayPalPlugin\Changer\PaymentMethodChanger;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderChecker;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderCheckerInterface;
use Sylius\PayPalPlugin\Checker\PayerActionChecker;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationChecker;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;
use Sylius\PayPalPlugin\Checker\PaymentCompletionChecker;
use Sylius\PayPalPlugin\Checker\PaymentCompletionCheckerInterface;
use Sylius\PayPalPlugin\Checker\PaymentRefundChecker;
use Sylius\PayPalPlugin\Checker\PaymentRefundCheckerInterface;
use Sylius\PayPalPlugin\Checker\PaymentSourceEnabledChecker;
use Sylius\PayPalPlugin\Checker\PaymentSourceEnabledCheckerInterface;
use Sylius\PayPalPlugin\Completer\ExpressCheckoutCompleter;
use Sylius\PayPalPlugin\Completer\ExpressCheckoutCompleterInterface;
use Sylius\PayPalPlugin\Console\Command\CompletePaidPaymentsCommand;
use Sylius\PayPalPlugin\Console\Command\PruneWebhookEventsCommand;
use Sylius\PayPalPlugin\Console\Command\RegisterWebhookEventTypesCommand;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreator;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreator;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Downloader\ReportDownloaderInterface;
use Sylius\PayPalPlugin\Downloader\SftpPayoutsReportDownloader;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Enabler\PayPalPaymentMethodEnabler;
use Sylius\PayPalPlugin\Entity\PayPalWebhookEvent;
use Sylius\PayPalPlugin\EventListener\RemoveCachedAccessTokenListener;
use Sylius\PayPalPlugin\EventListener\RemovePayPalPaymentDetailsListener;
use Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactory;
use Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactoryInterface;
use Sylius\PayPalPlugin\Factory\ModeSwitchViewFactory;
use Sylius\PayPalPlugin\Factory\ModeSwitchViewFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalItemFactory;
use Sylius\PayPalPlugin\Factory\PayPalItemFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactory;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactory;
use Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactoryInterface;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactory;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Factory\SftpClientFactory;
use Sylius\PayPalPlugin\Factory\SftpClientFactoryInterface;
use Sylius\PayPalPlugin\Factory\ShippingCallbackResponseFactory;
use Sylius\PayPalPlugin\Factory\ShippingCallbackResponseFactoryInterface;
use Sylius\PayPalPlugin\Factory\ShippingOptionsFactory;
use Sylius\PayPalPlugin\Factory\ShippingOptionsFactoryInterface;
use Sylius\PayPalPlugin\Form\Extension\GatewayConfigTypeExtension;
use Sylius\PayPalPlugin\Form\Extension\PaymentMethodTypeExtension;
use Sylius\PayPalPlugin\Form\Type\PayPalConfigurationType;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGenerator;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Listener\PayPalPaymentMethodListener;
use Sylius\PayPalPlugin\Manager\CredentialsManager;
use Sylius\PayPalPlugin\Manager\CredentialsManagerInterface;
use Sylius\PayPalPlugin\Mapper\PayPalOrderDetailsMapper;
use Sylius\PayPalPlugin\Mapper\PayPalOrderDetailsMapperInterface;
use Sylius\PayPalPlugin\Processor\AfterCheckoutOrderPaymentProcessor;
use Sylius\PayPalPlugin\Processor\LocaleProcessor;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentAuthorizationProcessor;
use Sylius\PayPalPlugin\Processor\PaymentAuthorizationProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessor;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalAddressProcessor;
use Sylius\PayPalPlugin\Processor\PayPalLateCaptureRefundConfirmationProcessor;
use Sylius\PayPalPlugin\Processor\PayPalLateCaptureRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundConfirmationProcessor;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;
use Sylius\PayPalPlugin\Provider\ActiveModeProvider;
use Sylius\PayPalPlugin\Provider\ActiveModeProviderInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProvider;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\ChannelAvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProvider;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProviderInterface;
use Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProvider;
use Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProviderInterface;
use Sylius\PayPalPlugin\Provider\EnabledPaymentSourcesProvider;
use Sylius\PayPalPlugin\Provider\EnabledPaymentSourcesProviderInterface;
use Sylius\PayPalPlugin\Provider\ExperienceContextProvider;
use Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface;
use Sylius\PayPalPlugin\Provider\HostProvider;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
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
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemover;
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemoverInterface;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepository;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepositoryInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentBatchQueryInterface;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQuery;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;
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
use Sylius\PayPalPlugin\Resolver\WebhookEventPaymentResolver;
use Sylius\PayPalPlugin\Resolver\WebhookEventPaymentResolverInterface;
use Sylius\PayPalPlugin\Twig\Component\PayPalOnboardingModalComponent;
use Sylius\PayPalPlugin\Twig\EnabledPaymentSourcesExtension;
use Sylius\PayPalPlugin\Twig\OrderAddressExtension;
use Sylius\PayPalPlugin\Twig\PayPalExtension;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdater;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;
use Sylius\PayPalPlugin\Updater\PayPalPaymentUpdater;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifier;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifier;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifier;
use Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifierInterface;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifier;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifier;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifierInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $container->import('services/**/*.php');

    $parameters->set('sylius_paypal.prioritize_paypal_as_default_method', true);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.updatable_states', [PaymentInterface::STATE_CART]);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.cancellable_states', [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED]);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.settleable_states', [PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED, PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_CANCELLED, PaymentInterface::STATE_FAILED]);

    $services->set('sylius_paypal.form.extension.payment_method', PaymentMethodTypeExtension::class)
        ->tag('form.type_extension');

    $services->set('sylius_paypal.form.extension.gateway_config', GatewayConfigTypeExtension::class)
        ->tag('form.type_extension');

    $services->set('sylius_paypal.form.type.paypal_configuration', PayPalConfigurationType::class)
        ->args([
            service('sylius_paypal.manager.credentials'),
            service('translator'),
        ])
        ->tag('form.type')
        ->tag('sylius.gateway_configuration_type', ['type' => 'sylius_paypal', 'label' => 'sylius_paypal.label']);

    $services->set('sylius_paypal.generator.paypal_auth_assertion', PayPalAuthAssertionGenerator::class);

    $services->alias(PayPalAuthAssertionGeneratorInterface::class, 'sylius_paypal.generator.paypal_auth_assertion');

    $services->set('sylius_paypal.applicator.payment_capture', PaymentCaptureApplicator::class)
        ->public()
        ->args([service('sylius_paypal.dispatcher.capture_authorization_payment_request')]);

    $services->alias(PaymentCaptureApplicatorInterface::class, 'sylius_paypal.applicator.payment_capture');

    $services->set('sylius_paypal.applicator.payment_refund', PaymentRefundApplicator::class)
        ->public()
        ->args([service('sylius_paypal.dispatcher.refund_paypal_payment_request')]);

    $services->alias(PaymentRefundApplicatorInterface::class, 'sylius_paypal.applicator.payment_refund');

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

    $services->set('sylius_paypal.listener.remove_cached_access_token', RemoveCachedAccessTokenListener::class)
        ->args([service('sylius_paypal.remover.cached_access_token')])
        ->tag('kernel.event_listener', ['event' => 'sylius.payment_method.pre_update']);

    $services->set('sylius_paypal.listener.remove_paypal_payment_details', RemovePayPalPaymentDetailsListener::class)
        ->args([service('sylius_paypal.remover.payment_details')])
        ->tag('kernel.event_listener', ['event' => 'sylius.order.pre_update']);

    $services->set('sylius_paypal.completer.express_checkout', ExpressCheckoutCompleter::class)
        ->args([
            service('sylius_paypal.api.order_details'),
            service('sylius_paypal.api.update_order'),
            service('sylius.repository.customer'),
            service('sylius.factory.customer'),
            service('sylius_paypal.factory.express_order_address'),
            service('sylius.repository.shipping_method'),
            service('sylius_paypal.verifier.payment_amount'),
            service('sylius_abstraction.state_machine'),
            service('sylius.order_processing.order_processor'),
            service('sylius.manager.order'),
        ]);

    $services->alias(ExpressCheckoutCompleterInterface::class, 'sylius_paypal.completer.express_checkout');

    $services->set('sylius_paypal.mapper.paypal_order_details', PayPalOrderDetailsMapper::class);

    $services->alias(PayPalOrderDetailsMapperInterface::class, 'sylius_paypal.mapper.paypal_order_details');

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
            service('sylius_paypal.checker.payment_authorization'),
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
        ->args([service('router'), service('monolog.logger.paypal')]);

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
        ->args([
            service('sylius_paypal.repository.query.paypal_payment_method'),
        ]);

    $services->alias(PayPalPaymentMethodProviderInterface::class, 'sylius_paypal.provider.paypal_payment_method');

    $services->set('sylius_paypal.provider.active_mode', ActiveModeProvider::class)
        ->args([service('sylius_paypal.provider.paypal_payment_method')]);

    $services->alias(ActiveModeProviderInterface::class, 'sylius_paypal.provider.active_mode');

    $services->set('sylius_paypal.provider.host', HostProvider::class)
        ->args([service('sylius_paypal.provider.active_mode')]);

    $services->alias(HostProviderInterface::class, 'sylius_paypal.provider.host');

    $services->set('sylius_paypal.manager.credentials', CredentialsManager::class);

    $services->alias(CredentialsManagerInterface::class, 'sylius_paypal.manager.credentials');

    $services->set('sylius_paypal.provider.available_countries', AvailableCountriesProvider::class)
        ->args([
            service('sylius.repository.country'),
            service('sylius.context.channel'),
        ]);

    $services->alias(AvailableCountriesProviderInterface::class, 'sylius_paypal.provider.available_countries');

    $services->alias(ChannelAvailableCountriesProviderInterface::class, 'sylius_paypal.provider.available_countries');

    $services->set('sylius_paypal.resolver.capture_payment', CapturePaymentResolver::class)
        ->args([service('sylius_paypal.creator.paypal_order')]);

    $services->alias(CapturePaymentResolverInterface::class, 'sylius_paypal.resolver.capture_payment');

    $services->set('sylius_paypal.order_processing.order_payment_processor.after_checkout', AfterCheckoutOrderPaymentProcessor::class)
        ->decorate('sylius.order_processing.order_payment_processor.after_checkout')
        ->args([service('.inner')]);

    $services->set('sylius_paypal.updater.approved_paypal_order', ApprovedPayPalOrderUpdater::class)
        ->args([
            service('sylius_paypal.api.update_order'),
            service('sylius_paypal.api.update_order_address'),
            service('sylius_paypal.updater.payment'),
            service('sylius.state_resolver.order_payment'),
        ]);

    $services->alias(ApprovedPayPalOrderUpdaterInterface::class, 'sylius_paypal.updater.approved_paypal_order');

    $services->set('sylius_paypal.checker.approved_paypal_order', ApprovedPayPalOrderChecker::class);

    $services->alias(ApprovedPayPalOrderCheckerInterface::class, 'sylius_paypal.checker.approved_paypal_order');

    $services->set('sylius_paypal.processor.payment_capture', PaymentCaptureProcessor::class)
        ->args([
            service('sylius_paypal.checker.approved_paypal_order'),
            service('sylius_paypal.updater.approved_paypal_order'),
            service('sylius_paypal.api.complete_order'),
            service('sylius_paypal.api.order_details'),
        ]);

    $services->alias(PaymentCaptureProcessorInterface::class, 'sylius_paypal.processor.payment_capture');

    $services->set('sylius_paypal.processor.payment_authorization', PaymentAuthorizationProcessor::class)
        ->args([
            service('sylius_paypal.checker.approved_paypal_order'),
            service('sylius_paypal.updater.approved_paypal_order'),
            service('sylius_paypal.api.authorize_order'),
            service('sylius_paypal.api.order_details'),
        ]);

    $services->alias(PaymentAuthorizationProcessorInterface::class, 'sylius_paypal.processor.payment_authorization');

    $services->set('sylius_paypal.processor.locale', LocaleProcessor::class)
        ->args([service('sylius_paypal.resolver.supported_locale')]);

    $services->alias(LocaleProcessorInterface::class, 'sylius_paypal.processor.locale');

    $services->set('sylius_paypal.provider.current_paypal_locale', CurrentPayPalLocaleProvider::class)
        ->args([
            service('sylius.context.locale'),
            service('sylius_paypal.processor.locale'),
        ]);

    $services->alias(CurrentPayPalLocaleProviderInterface::class, 'sylius_paypal.provider.current_paypal_locale');

    $services->set('sylius_paypal.resolver.payment_method.paypal', PayPalDefaultPaymentMethodResolver::class)
        ->decorate('sylius.resolver.payment_method.default')
        ->args([
            service('.inner'),
            service('sylius.repository.payment_method'),
            param('sylius_paypal.prioritize_paypal_as_default_method'),
        ]);

    $services->set('sylius_paypal.resolver.payment_method.paypal_prioritising', PayPalPrioritisingPaymentMethodsResolver::class)
        ->decorate('sylius.resolver.payment_methods')
        ->args([
            service('.inner'),
            param('sylius_paypal.prioritized_factory_name'),
        ]);

    $services->set('sylius_paypal.provider.paypal_configuration', PayPalConfigurationProvider::class)
        ->args([service('sylius.repository.payment_method')]);

    $services->alias(PayPalConfigurationProviderInterface::class, 'sylius_paypal.provider.paypal_configuration');

    $services->alias(PayPalFundingSourcesConfigurationProviderInterface::class, 'sylius_paypal.provider.paypal_configuration');

    $services->set('sylius_paypal.provider.web_sdk_configuration', WebSdkConfigurationProvider::class)
        ->args([
            service('sylius_paypal.provider.paypal_configuration'),
            service('sylius_paypal.provider.host'),
            service('sylius_paypal.provider.active_mode'),
            param('sylius_paypal.test_buyer_country'),
        ]);

    $services->alias(WebSdkConfigurationProviderInterface::class, 'sylius_paypal.provider.web_sdk_configuration');

    $services->set('sylius_paypal.provider.paypal_payment_page_context', PayPalPaymentPageContextProvider::class)
        ->args([
            service('sylius_paypal.provider.web_sdk_configuration'),
            service('router'),
            service('sylius_paypal.processor.locale'),
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
            service('sylius_paypal.provider.eligible_redirect_payment_sources'),
            service('sylius_paypal.checker.payment_authorization'),
        ]);

    $services->alias(PayPalPaymentPageContextProviderInterface::class, 'sylius_paypal.provider.paypal_payment_page_context');

    $services->set('sylius_paypal.provider.uuid', UuidProvider::class);

    $services->alias(UuidProviderInterface::class, 'sylius_paypal.provider.uuid');

    $services->set('sylius_paypal.factory.sftp_client', SftpClientFactory::class);

    $services->alias(SftpClientFactoryInterface::class, 'sylius_paypal.factory.sftp_client');

    $services->set('sylius_paypal.downloader.report', SftpPayoutsReportDownloader::class)
        ->args([
            service('sylius_paypal.factory.sftp_client'),
            service('sylius_paypal.provider.host'),
        ]);

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
            service('sylius_paypal.api.find_eligible_methods'),
            service('sylius_paypal.provider.paypal_configuration'),
            service('monolog.logger.paypal'),
            service('sylius_paypal.checker.payment_authorization'),
        ]);

    $services->alias(EligibleRedirectPaymentSourcesProviderInterface::class, 'sylius_paypal.provider.eligible_redirect_payment_sources');

    $services->set('sylius_paypal.provider.enabled_payment_sources', EnabledPaymentSourcesProvider::class);

    $services->alias(EnabledPaymentSourcesProviderInterface::class, 'sylius_paypal.provider.enabled_payment_sources');

    $services->set('sylius_paypal.verifier.webhook_request', WebhookRequestVerifier::class)
        ->args([
            service('sylius_paypal.provider.webhook_id'),
            service('sylius_paypal.api.webhook_signature_verifier'),
        ]);

    $services->alias(WebhookRequestVerifierInterface::class, 'sylius_paypal.verifier.webhook_request');

    $services->set('sylius_paypal.checker.payer_action', PayerActionChecker::class);

    $services->alias(PayerActionCheckerInterface::class, 'sylius_paypal.checker.payer_action');

    $services->set('sylius_paypal.checker.payment_source_enabled', PaymentSourceEnabledChecker::class)
        ->args([service('sylius_paypal.checker.payment_authorization')]);

    $services->alias(PaymentSourceEnabledCheckerInterface::class, 'sylius_paypal.checker.payment_source_enabled');

    $services->set('sylius_paypal.checker.payment_authorization', PaymentAuthorizationChecker::class)
        ->args([param('sylius_paypal.authorize.payment_sources')]);

    $services->alias(PaymentAuthorizationCheckerInterface::class, 'sylius_paypal.checker.payment_authorization');

    $services->set('sylius_paypal.processor.payment_settlement', PayPalPaymentSettlementProcessor::class)
        ->args([
            service('sylius_paypal.api.order_details'),
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.payment'),
            service('monolog.logger.paypal'),
        ]);

    $services->alias(PaymentSettlementProcessorInterface::class, 'sylius_paypal.processor.payment_settlement');

    $services->set('sylius_paypal.processor.payment_refund_confirmation', PayPalPaymentRefundConfirmationProcessor::class)
        ->args([
            service('sylius.repository.payment_request'),
            service('sylius_abstraction.state_machine'),
        ]);

    $services->alias(PayPalPaymentRefundConfirmationProcessorInterface::class, 'sylius_paypal.processor.payment_refund_confirmation');

    $services->set('sylius_paypal.processor.late_capture_refund_confirmation', PayPalLateCaptureRefundConfirmationProcessor::class)
        ->args([
            service('sylius.repository.payment_request'),
            service('sylius_abstraction.state_machine'),
        ]);

    $services->alias(PayPalLateCaptureRefundConfirmationProcessorInterface::class, 'sylius_paypal.processor.late_capture_refund_confirmation');

    $services->set('sylius_paypal.checker.payment_completion', PaymentCompletionChecker::class)
        ->public();

    $services->alias(PaymentCompletionCheckerInterface::class, 'sylius_paypal.checker.payment_completion');

    $services->set('sylius_paypal.checker.payment_refund', PaymentRefundChecker::class)
        ->public();

    $services->alias(PaymentRefundCheckerInterface::class, 'sylius_paypal.checker.payment_refund');

    $services->set('sylius_paypal.provider.webhook_url', WebhookUrlProvider::class)
        ->args([
            service('router'),
            param('sylius_paypal.webhook_base_url'),
        ]);

    $services->alias(WebhookUrlProviderInterface::class, 'sylius_paypal.provider.webhook_url');

    $services->set('sylius_paypal.registrar.seller_webhook_event_types', SellerWebhookEventTypesRegistrar::class)
        ->args([
            service('sylius_paypal.provider.webhook_id.lookup'),
            service('sylius_paypal.api.update_webhook'),
            service('sylius_paypal.api.webhook'),
            service('sylius_paypal.provider.webhook_url'),
            service('sylius_paypal.updater.webhook_id'),
        ]);

    $services->alias(SellerWebhookEventTypesRegistrarInterface::class, 'sylius_paypal.registrar.seller_webhook_event_types');

    $services->set('sylius_paypal.console.command.register_webhook_event_types', RegisterWebhookEventTypesCommand::class)
        ->args([
            service('sylius.repository.payment_method'),
            service('sylius_paypal.registrar.seller_webhook_event_types'),
        ])
        ->tag('console.command');

    $services->set('sylius_paypal.repository.webhook_event', PayPalWebhookEventRepository::class)
        ->args([PayPalWebhookEvent::class])
        ->factory([service('doctrine.orm.entity_manager'), 'getRepository']);

    $services->alias(PayPalWebhookEventRepositoryInterface::class, 'sylius_paypal.repository.webhook_event');

    $services->set('sylius_paypal.resolver.webhook_event_payment', WebhookEventPaymentResolver::class)
        ->args([
            service('sylius_paypal.repository.query.paypal_payment'),
        ]);

    $services->alias(WebhookEventPaymentResolverInterface::class, 'sylius_paypal.resolver.webhook_event_payment');

    $services->set('sylius_paypal.console.command.prune_webhook_events', PruneWebhookEventsCommand::class)
        ->args([
            service('sylius_paypal.repository.webhook_event'),
            '%sylius_paypal.webhook_event_retention_days%',
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
            service('sylius_paypal.repository.query.paypal_payment'),
            service('doctrine.orm.entity_manager'),
        ])
        ->tag('console.command');

    $services->set('sylius_paypal.provider.refund_reference_number', RefundReferenceNumberProvider::class);

    $services->alias(RefundReferenceNumberProviderInterface::class, 'sylius_paypal.provider.refund_reference_number');

    $services->set('sylius_paypal.registrar.seller_webhook', SellerWebhookRegistrar::class)
        ->args([
            service('router'),
            service('sylius_paypal.api.webhook'),
            service('sylius_paypal.provider.webhook_url'),
        ]);

    $services->alias(SellerWebhookRegistrarInterface::class, 'sylius_paypal.registrar.seller_webhook');

    $services->set('sylius_paypal.creator.paypal_order', PayPalOrderCreator::class)
        ->args([
            service('sylius_paypal.api.create_order'),
            service('sylius_paypal.provider.uuid'),
            service('sylius_paypal.provider.paypal_order_created_statuses'),
        ]);

    $services->alias(PayPalOrderCreatorInterface::class, 'sylius_paypal.creator.paypal_order');

    $services->set('sylius_paypal.remover.payment_details', PayPalPaymentDetailsRemover::class)
        ->public();

    $services->alias(PayPalPaymentDetailsRemoverInterface::class, 'sylius_paypal.remover.payment_details');

    $services->set('sylius_paypal.changer.payment_method', PaymentMethodChanger::class)
        ->decorate('sylius_api.changer.payment_method', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service('.inner'),
            service('sylius_paypal.remover.payment_details'),
        ]);

    $services->set('sylius_paypal.creator.onboarding_payment_method', PayPalOnboardingPaymentMethodCreator::class)
        ->args([
            service('sylius.factory.gateway_config'),
            service('sylius.factory.payment_method'),
            service('doctrine.orm.entity_manager'),
            param('sylius_paypal.partner_attribution_id'),
            service('sylius_paypal.provider.paypal_payment_method'),
            service('sylius_paypal.manager.credentials'),
            service('sylius_paypal.remover.cached_access_token'),
        ]);

    $services->alias(PayPalOnboardingPaymentMethodCreatorInterface::class, 'sylius_paypal.creator.onboarding_payment_method');

    $services->set('sylius_paypal.factory.mode_switch_view', ModeSwitchViewFactory::class);

    $services->alias(ModeSwitchViewFactoryInterface::class, 'sylius_paypal.factory.mode_switch_view');

    $services->set('sylius_paypal.twig.extension.paypal', PayPalExtension::class)
        ->args([
            service('sylius_paypal.provider.active_mode'),
            service('sylius_paypal.factory.mode_switch_view'),
            service(PayPalFundingSourcesConfigurationProviderInterface::class),
            service('sylius.context.channel'),
            service(WebSdkConfigurationProviderInterface::class),
            service('sylius_paypal.checker.payer_action'),
            service('sylius_paypal.provider.current_paypal_locale'),
            service('sylius_paypal.provider.host'),
        ])
        ->tag('twig.extension');

    $services->set('sylius_paypal.twig.extension.order_address', OrderAddressExtension::class)
        ->tag('twig.extension');

    $services->set('sylius_paypal.twig.extension.enabled_payment_sources', EnabledPaymentSourcesExtension::class)
        ->args([service('sylius_paypal.provider.enabled_payment_sources')])
        ->tag('twig.extension');

    $services->set('sylius_paypal.twig.component.paypal_onboarding_modal', PayPalOnboardingModalComponent::class)
        ->args([
            service('sylius_paypal.provider.onboarding_url'),
            service('sylius_paypal.manager.seller_nonce'),
            service('sylius_paypal.onboarding.checker.seller_onboarded'),
            service('monolog.logger.paypal'),
        ])
        ->tag('sylius.live_component.admin', ['key' => 'sylius_paypal:live_onboarding_modal', 'template' => '@SyliusPayPalPlugin/admin/shared/components/paypal_onboarding_modal.html.twig']);

    $services->set('sylius_paypal.verifier.payment_amount', PaymentAmountVerifier::class);

    $services->alias(PaymentAmountVerifierInterface::class, 'sylius_paypal.verifier.payment_amount');

    $services->set('sylius_paypal.verifier.three_d_secure', ThreeDSecureVerifier::class);

    $services->alias(ThreeDSecureVerifierInterface::class, 'sylius_paypal.verifier.three_d_secure');

    $services->set('sylius_paypal.verifier.payment_three_d_secure', PaymentThreeDSecureVerifier::class)
        ->args([
            service('sylius_paypal.api.order_details'),
            service('sylius_paypal.verifier.three_d_secure'),
        ]);

    $services->alias(PaymentThreeDSecureVerifierInterface::class, 'sylius_paypal.verifier.payment_three_d_secure');

    $services->set('sylius_paypal.verifier.order_ownership', OrderOwnershipVerifier::class)
        ->args([service('sylius.context.cart')]);

    $services->alias(OrderOwnershipVerifierInterface::class, 'sylius_paypal.verifier.order_ownership');

    $services->set('sylius_paypal.repository.query.paypal_payment_method', PayPalPaymentMethodQuery::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            param('sylius.model.payment_method.class'),
        ]);

    $services->alias(PayPalPaymentMethodQueryInterface::class, 'sylius_paypal.repository.query.paypal_payment_method');

    $services->set('sylius_paypal.repository.query.paypal_payment', PaypalPaymentQuery::class)
        ->args([
            service('sylius.manager.payment'),
            service('sylius.repository.payment'),
            param('sylius_paypal.repository.query.pay_pal_payment.updatable_states'),
            param('sylius_paypal.repository.query.pay_pal_payment.cancellable_states'),
            param('sylius_paypal.repository.query.pay_pal_payment.settleable_states'),
        ]);

    $services->alias(PaypalPaymentQueryInterface::class, 'sylius_paypal.repository.query.paypal_payment');

    $services->alias(SettleablePaypalPaymentQueryInterface::class, 'sylius_paypal.repository.query.paypal_payment');

    $services->alias(PaypalPaymentBatchQueryInterface::class, 'sylius_paypal.repository.query.paypal_payment');

    $services->set('sylius_paypal.resolver.paypal_payment_methods', PayPalPaymentMethodsResolver::class)
        ->args([service('sylius.repository.payment_method')]);

    $services->alias(PayPalPaymentMethodsResolverInterface::class, 'sylius_paypal.resolver.paypal_payment_methods');

    $services->set('sylius_paypal.resolver.supported_locale', SupportedLocaleResolver::class)
        ->args([param('sylius_paypal.supported_locales')]);

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
