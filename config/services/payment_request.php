<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\Bundle\PaymentBundle\CommandProvider\ActionsCommandProvider;
use Sylius\Bundle\PaymentBundle\Provider\ActionsHttpResponseProvider;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\CommandHandler\StatusPaymentRequestHandler;
use Sylius\PayPalPlugin\CommandProvider\StatusPaymentRequestCommandProvider;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('sylius_paypal.command_provider', ActionsCommandProvider::class)
        ->args([tagged_locator('sylius_paypal.command_provider', 'action')])
        ->tag('sylius.payment_request.command_provider', ['gateway_factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);

    $services->set('sylius_paypal.command_provider.status', StatusPaymentRequestCommandProvider::class)
        ->tag('sylius_paypal.command_provider', ['action' => PaymentRequestInterface::ACTION_STATUS]);

    $services->set('sylius_paypal.provider.http_response', ActionsHttpResponseProvider::class)
        ->args([tagged_locator('sylius_paypal.provider.http_response', 'action')])
        ->tag('sylius.payment_request.provider.http_response', ['gateway_factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME]);

    $services->set('sylius_paypal.command_handler.status', StatusPaymentRequestHandler::class)
        ->args([
            service('sylius.provider.payment_request'),
            service('sylius_abstraction.state_machine'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);
};
