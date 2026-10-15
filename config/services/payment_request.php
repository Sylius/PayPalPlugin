<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\Bundle\PaymentBundle\CommandProvider\ActionsCommandProvider;
use Sylius\Bundle\PaymentBundle\Provider\ActionsHttpResponseProvider;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\CommandHandler\CapturePayPalOrderPaymentRequestHandler;
use Sylius\PayPalPlugin\CommandHandler\CreatePayPalOrderPaymentRequestHandler;
use Sylius\PayPalPlugin\CommandHandler\NotifyPaymentRequestHandler;
use Sylius\PayPalPlugin\CommandHandler\StatusPaymentRequestHandler;
use Sylius\PayPalPlugin\CommandProvider\CapturePaymentRequestCommandProvider;
use Sylius\PayPalPlugin\CommandProvider\NotifyPaymentRequestCommandProvider;
use Sylius\PayPalPlugin\CommandProvider\StatusPaymentRequestCommandProvider;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Dispatcher\CapturePayPalOrderPaymentRequestDispatcher;
use Sylius\PayPalPlugin\Dispatcher\CapturePayPalOrderPaymentRequestDispatcherInterface;
use Sylius\PayPalPlugin\OrderPay\Provider\CaptureHttpResponseProvider;
use Sylius\PayPalPlugin\Processor\Webhook\CaptureApprovedOrderWebhookEventProcessor;
use Sylius\PayPalPlugin\Processor\Webhook\RefundPaymentWebhookEventProcessor;
use Sylius\PayPalPlugin\Processor\Webhook\SettlePaymentWebhookEventProcessor;
use Sylius\PayPalPlugin\Provider\PayPalNotifyPaymentProvider;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('sylius_paypal.command_provider', ActionsCommandProvider::class)
        ->args([tagged_locator('sylius_paypal.command_provider', 'action')])
        ->tag('sylius.payment_request.command_provider', ['gateway_factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);

    $services->set('sylius_paypal.command_provider.capture', CapturePaymentRequestCommandProvider::class)
        ->tag('sylius_paypal.command_provider', ['action' => PaymentRequestInterface::ACTION_CAPTURE]);

    $services->set('sylius_paypal.command_provider.status', StatusPaymentRequestCommandProvider::class)
        ->tag('sylius_paypal.command_provider', ['action' => PaymentRequestInterface::ACTION_STATUS]);

    $services->set('sylius_paypal.command_provider.notify', NotifyPaymentRequestCommandProvider::class)
        ->tag('sylius_paypal.command_provider', ['action' => PaymentRequestInterface::ACTION_NOTIFY]);

    $services->set('sylius_paypal.provider.notify_payment', PayPalNotifyPaymentProvider::class)
        ->args([
            service('sylius_paypal.verifier.webhook_request'),
            service('sylius_paypal.resolver.webhook_event_payment'),
        ])
        ->tag('sylius.payment_request.payment_notify_provider', ['priority' => 10]);

    $services->set('sylius_paypal.provider.http_response', ActionsHttpResponseProvider::class)
        ->args([tagged_locator('sylius_paypal.provider.http_response', 'action')])
        ->tag('sylius.payment_request.provider.http_response', ['gateway_factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);

    $services->set('sylius_paypal.provider.http_response.capture', CaptureHttpResponseProvider::class)
        ->args([
            service('twig'),
            service('sylius_paypal.provider.paypal_payment_page_context'),
            service('router'),
            service('sylius_paypal.provider.host'),
        ])
        ->tag('sylius_paypal.provider.http_response', ['action' => PaymentRequestInterface::ACTION_CAPTURE]);

    $services->set('sylius_paypal.provider.payment_request_return_url')
        ->parent('sylius.provider.order_pay.payment_request_pay_url')
        ->args([
            '%sylius_shop.order_pay.payment_request_pay_route%',
            [
                'hash' => 'paymentRequest.getHash()',
                '_locale' => 'payment.getOrder().getLocaleCode()',
            ],
        ]);

    $services->set('sylius_paypal.command_handler.create_paypal_order', CreatePayPalOrderPaymentRequestHandler::class)
        ->args([
            service('sylius.provider.payment_request'),
            service('sylius_paypal.creator.paypal_order'),
            service('sylius_paypal.provider.paypal_payment_source'),
            service('sylius_paypal.checker.payment_source_enabled'),
            service('sylius_paypal.provider.payment_request_return_url'),
            service('sylius_abstraction.state_machine'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    $services->set('sylius_paypal.command_handler.capture_paypal_order', CapturePayPalOrderPaymentRequestHandler::class)
        ->args([
            service('sylius.provider.payment_request'),
            service('sylius_paypal.verifier.payment_three_d_secure'),
            service('sylius_paypal.processor.payment_capture'),
            service('sylius_paypal.processor.payment_settlement'),
            service('sylius_abstraction.state_machine'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    $services->set('sylius_paypal.command_handler.status', StatusPaymentRequestHandler::class)
        ->args([
            service('sylius.provider.payment_request'),
            service('sylius_paypal.processor.payment_settlement'),
            service('sylius_abstraction.state_machine'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    $services->set('sylius_paypal.dispatcher.capture_paypal_order_payment_request', CapturePayPalOrderPaymentRequestDispatcher::class)
        ->args([
            service('sylius.factory.payment_request'),
            service('sylius.repository.payment_request'),
            service('sylius_abstraction.state_machine'),
            service('sylius.payment_request.command_bus'),
        ]);

    $services->alias(CapturePayPalOrderPaymentRequestDispatcherInterface::class, 'sylius_paypal.dispatcher.capture_paypal_order_payment_request');

    $services->set('sylius_paypal.command_handler.notify', NotifyPaymentRequestHandler::class)
        ->args([
            service('sylius.provider.payment_request'),
            service('sylius_paypal.repository.webhook_event'),
            tagged_locator('sylius_paypal.webhook_event_processor', 'event_type'),
            service('sylius_paypal.api.webhook_resource'),
            service('sylius_abstraction.state_machine'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    $settlePayment = $services->set('sylius_paypal.processor.webhook_event.settle_payment', SettlePaymentWebhookEventProcessor::class)
        ->args([service('sylius_paypal.processor.payment_settlement')]);
    foreach ([...SettlePaymentWebhookEventProcessor::CAPTURE_EVENT_TYPES, SettlePaymentWebhookEventProcessor::ORDER_EVENT_TYPE] as $eventType) {
        $settlePayment->tag('sylius_paypal.webhook_event_processor', ['event_type' => $eventType]);
    }

    $services->set('sylius_paypal.processor.webhook_event.refund_payment', RefundPaymentWebhookEventProcessor::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius.manager.payment'),
            service('monolog.logger.paypal'),
        ])
        ->tag('sylius_paypal.webhook_event_processor', ['event_type' => RefundPaymentWebhookEventProcessor::REFUNDED_EVENT_TYPE])
        ->tag('sylius_paypal.webhook_event_processor', ['event_type' => RefundPaymentWebhookEventProcessor::REVERSED_EVENT_TYPE]);

    $services->set('sylius_paypal.processor.webhook_event.capture_approved_order', CaptureApprovedOrderWebhookEventProcessor::class)
        ->args([
            service('sylius.repository.payment_request'),
            service('sylius.command_provider.payment_request.default'),
            service('sylius.payment_request.command_bus'),
            '%sylius_paypal.webhook_approved_order_capture_delay%',
        ])
        ->tag('sylius_paypal.webhook_event_processor', ['event_type' => CaptureApprovedOrderWebhookEventProcessor::EVENT_TYPE]);
};
