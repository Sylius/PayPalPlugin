<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\Bundle\PaymentBundle\CommandProvider\ActionsCommandProvider;
use Sylius\Bundle\PaymentBundle\Provider\ActionsHttpResponseProvider;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\CommandHandler\CaptureEndPaymentRequestHandler;
use Sylius\PayPalPlugin\CommandHandler\CapturePaymentRequestHandler;
use Sylius\PayPalPlugin\CommandHandler\StatusPaymentRequestHandler;
use Sylius\PayPalPlugin\CommandProvider\CapturePaymentRequestCommandProvider;
use Sylius\PayPalPlugin\CommandProvider\StatusPaymentRequestCommandProvider;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\OrderPay\Provider\CaptureHttpResponseProvider;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('sylius_paypal.command_provider', ActionsCommandProvider::class)
        ->args([tagged_locator('sylius_paypal.command_provider', 'action')])
        ->tag('sylius.payment_request.command_provider', ['gateway_factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);

    $services->set('sylius_paypal.command_provider.capture', CapturePaymentRequestCommandProvider::class)
        ->tag('sylius_paypal.command_provider', ['action' => PaymentRequestInterface::ACTION_CAPTURE]);

    $services->set('sylius_paypal.command_provider.status', StatusPaymentRequestCommandProvider::class)
        ->tag('sylius_paypal.command_provider', ['action' => PaymentRequestInterface::ACTION_STATUS]);

    $services->set('sylius_paypal.provider.http_response', ActionsHttpResponseProvider::class)
        ->args([tagged_locator('sylius_paypal.provider.http_response', 'action')])
        ->tag('sylius.payment_request.provider.http_response', ['gateway_factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);

    $services->set('sylius_paypal.provider.http_response.capture', CaptureHttpResponseProvider::class)
        ->args([
            service('twig'),
            service('sylius_paypal.provider.paypal_payment_page_context'),
            service('router'),
            '%sylius_paypal.web_url%',
        ])
        ->tag('sylius_paypal.provider.http_response', ['action' => PaymentRequestInterface::ACTION_CAPTURE]);

    $services->set('sylius_paypal.command_handler.capture', CapturePaymentRequestHandler::class)
        ->args([
            service('sylius.provider.payment_request'),
            service('sylius_paypal.creator.paypal_order'),
            service('sylius_paypal.provider.paypal_payment_source'),
            service('router'),
            service('sylius_abstraction.state_machine'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    $services->set('sylius_paypal.command_handler.capture_end', CaptureEndPaymentRequestHandler::class)
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
};
