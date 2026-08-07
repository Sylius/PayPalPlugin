<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use phpseclib3\Net\SFTP;
use Psr\Http\Message\RequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\PayPalPlugin\ApiPlatform\PayPalPayment;
use Sylius\PayPalPlugin\Console\Command\CompletePaidPaymentsCommand;
use Sylius\PayPalPlugin\Creator\PayPalSandboxPaymentMethodCreator;
use Sylius\PayPalPlugin\Downloader\ReportDownloaderInterface;
use Sylius\PayPalPlugin\Downloader\SftpPayoutsReportDownloader;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Enabler\PayPalPaymentMethodEnabler;
use Sylius\PayPalPlugin\Factory\PayPalPaymentMethodNewResourceFactory;
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
use Sylius\PayPalPlugin\Processor\PayPalAddressProcessor;
use Sylius\PayPalPlugin\Processor\PayPalOrderCompleteProcessor;
use Sylius\PayPalPlugin\Processor\PayPalPaymentCompleteProcessor;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundProcessor;
use Sylius\PayPalPlugin\Processor\UiPayPalPaymentRefundProcessor;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProvider;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
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
use Sylius\PayPalPlugin\Provider\PayPalItemDataProvider;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProvider;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProvider;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProvider;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrar;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQuery;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolver;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Sylius\PayPalPlugin\Resolver\PayPalDefaultPaymentMethodResolver;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolver;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPrioritisingPaymentMethodsResolver;
use Sylius\PayPalPlugin\Resolver\SupportedLocaleResolver;
use Sylius\PayPalPlugin\Resolver\SupportedLocaleResolverInterface;
use Sylius\PayPalPlugin\Twig\Component\PayPalSandboxModalComponent;
use Sylius\PayPalPlugin\Twig\OrderAddressExtension;
use Sylius\PayPalPlugin\Twig\PayPalExtension;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;
use Sylius\PayPalPlugin\Updater\PayPalPaymentUpdater;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifier;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $container->import('services/**/*.php');

    $parameters->set('sylius_paypal.prioritize_paypal_as_default_method', true);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.updatable_states', [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW]);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.cancellable_states', [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED]);
    $parameters->set('sylius_paypal.repository.query.pay_pal_payment.refundable_states', [PaymentInterface::STATE_COMPLETED]);

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
        ])
        ->tag('sylius.api.payment_method_handler');

    $services->set('sylius_paypal.listener.paypal_payment_method', PayPalPaymentMethodListener::class)
        ->args([
            service('sylius_paypal.onboarding.initiator'),
            service('router'),
            service('request_stack'),
            service('sylius_paypal.provider.paypal_payment_method'),
            '%sylius_paypal.sandbox%',
        ])
        ->tag('kernel.event_listener', ['event' => 'sylius.payment_method.initialize_create', 'method' => 'initializeCreate']);

    $services->set('sylius_paypal.manager.payment_state', PaymentStateManager::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.payment'),
            service('sylius_paypal.processor.payment_complete'),
        ]);

    $services->alias(PaymentStateManagerInterface::class, 'sylius_paypal.manager.payment_state');

    $services->set('sylius_paypal.factory.paypal_payment_method_new_resource', PayPalPaymentMethodNewResourceFactory::class)
        ->decorate('sylius.resource_controller.new_resource_factory')
        ->args([
            service('.inner'),
            service('sylius_paypal.onboarding.processor.basic'),
        ]);

    $services->set('sylius_paypal.provider.order', OrderProvider::class)
        ->args([service('sylius.repository.order')]);

    $services->alias(OrderProviderInterface::class, 'sylius_paypal.provider.order');

    $services->set('sylius_paypal.provider.payment', PaymentProvider::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius.repository.order'),
        ])
        ->deprecate('sylius/paypal-plugin', '1.7', 'The "%service_id%" service is deprecated since 1.7 and will be removed in 3.0.');

    $services->alias(PaymentProviderInterface::class, 'sylius_paypal.provider.payment');

    $services->set('sylius_paypal.provider.order_item_non_neutral_tax', OrderItemNonNeutralTaxesProvider::class);

    $services->alias(OrderItemNonNeutralTaxesProviderInterface::class, 'sylius_paypal.provider.order_item_non_neutral_tax');

    $services->set('sylius_paypal.provider.paypal_item_data', PayPalItemDataProvider::class)
        ->args([service('sylius_paypal.provider.order_item_non_neutral_tax')]);

    $services->alias(PayPalItemDataProviderInterface::class, 'sylius_paypal.provider.paypal_item_data');

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

    $services->set('sylius_paypal.provider.uuid', UuidProvider::class);

    $services->alias(UuidProviderInterface::class, 'sylius_paypal.provider.uuid');

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
            service('sylius.http_client'),
            '%sylius_paypal.facilitator_url%',
            service('sylius.manager.payment_method'),
            service('sylius_paypal.registrar.seller_webhook'),
            service(RequestFactoryInterface::class),
        ]);

    $services->alias(PaymentMethodEnablerInterface::class, 'sylius_paypal.enabler.payment_method');

    $services->set('sylius_paypal.provider.payment_reference_number', PaymentReferenceNumberProvider::class);

    $services->alias(PaymentReferenceNumberProviderInterface::class, 'sylius_paypal.provider.payment_reference_number');

    $services->set('sylius_paypal.console.command.complete_paid_payments', CompletePaidPaymentsCommand::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius.manager.payment'),
            service('sylius_paypal.api.cache_authorize_client'),
            service('sylius_paypal.api.order_details'),
            service('sylius_abstraction.state_machine'),
        ])
        ->tag('console.command');

    $services->set('sylius_paypal.provider.refund_reference_number', RefundReferenceNumberProvider::class);

    $services->alias(RefundReferenceNumberProviderInterface::class, 'sylius_paypal.provider.refund_reference_number');

    $services->set('sylius_paypal.registrar.seller_webhook', SellerWebhookRegistrar::class)
        ->args([
            service('sylius_paypal.api.authorize_client'),
            service('router'),
            service('sylius_paypal.api.webhook'),
        ]);

    $services->alias(SellerWebhookRegistrarInterface::class, 'sylius_paypal.registrar.seller_webhook');

    $services->set('sylius_paypal.creator.sandbox_payment_method', PayPalSandboxPaymentMethodCreator::class)
        ->args([
            service('sylius.factory.gateway_config'),
            service('sylius.factory.payment_method'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('sylius_paypal.twig.extension.paypal', PayPalExtension::class)
        ->args(['%sylius_paypal.sandbox%'])
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
        ->tag('sylius.live_component.admin', ['key' => 'sylius_paypal:create_sandbox_modal', 'template' => '@SyliusPayPalPlugin/admin/shared/components/paypal_sandbox_modal.html.twig']);

    $services->set('sylius_paypal.verifier.payment_amount', PaymentAmountVerifier::class);

    $services->alias(PaymentAmountVerifierInterface::class, 'sylius_paypal.verifier.payment_amount');

    $services->set('sylius_paypal.repository.query.paypal_payment', PaypalPaymentQuery::class)
        ->args([
            service('sylius.manager.payment'),
            service('sylius.repository.payment'),
            '%sylius_paypal.repository.query.pay_pal_payment.updatable_states%',
            '%sylius_paypal.repository.query.pay_pal_payment.cancellable_states%',
            '%sylius_paypal.repository.query.pay_pal_payment.refundable_states%',
        ]);

    $services->alias(PaypalPaymentQueryInterface::class, 'sylius_paypal.repository.query.paypal_payment');

    $services->set('sylius_paypal.resolver.paypal_payment_methods', PayPalPaymentMethodsResolver::class)
        ->args([service('sylius.repository.payment_method')]);

    $services->alias(PayPalPaymentMethodsResolverInterface::class, 'sylius_paypal.resolver.paypal_payment_methods');

    $services->set('sylius_paypal.resolver.supported_locale', SupportedLocaleResolver::class)
        ->args(['%sylius_paypal.supported_locales%']);

    $services->alias(SupportedLocaleResolverInterface::class, 'sylius_paypal.resolver.supported_locale');
};
