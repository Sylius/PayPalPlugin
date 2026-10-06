<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Payum\Core\Bridge\Symfony\Builder\GatewayFactoryBuilder;
use Sylius\PayPalPlugin\Payum\Action\AuthorizeAction;
use Sylius\PayPalPlugin\Payum\Action\CaptureAction;
use Sylius\PayPalPlugin\Payum\Action\CompleteOrderAction;
use Sylius\PayPalPlugin\Payum\Action\ResolveNextRouteAction;
use Sylius\PayPalPlugin\Payum\Factory\PayPalGatewayFactory;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_paypal.gateway_factory_builder', GatewayFactoryBuilder::class)
        ->args([PayPalGatewayFactory::class])
        ->tag('payum.gateway_factory_builder', ['factory' => 'sylius_paypal']);

    $services->set('sylius_paypal.payum.action.authorize', AuthorizeAction::class)
        ->public()
        ->tag('payum.action', ['factory' => 'sylius_paypal', 'alias' => 'payum.action.authorize']);

    $services->set('sylius_paypal.payum.action.capture', CaptureAction::class)
        ->public()
        ->args([service('sylius_paypal.creator.paypal_order')])
        ->tag('payum.action', ['factory' => 'sylius_paypal', 'alias' => 'payum.action.capture']);

    $services->set('sylius_paypal.payum.action.complete_order', CompleteOrderAction::class)
        ->public()
        ->args([
            service('sylius_paypal.processor.payment_capture'),
            service('monolog.logger.paypal'),
        ])
        ->tag('payum.action', ['factory' => 'sylius_paypal', 'alias' => 'payum.action.complete_order']);

    $services->set('sylius_paypal.payum.action.resolve_next_route', ResolveNextRouteAction::class)
        ->public()
        ->tag('payum.action', ['factory' => 'sylius_paypal', 'alias' => 'sylius.resolve_next_route']);
};
