<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\PayPalPlugin\EventListener\Workflow\CompletePayPalOrderListener;
use Sylius\PayPalPlugin\EventListener\Workflow\RefundPaymentListener;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_paypal.listener.workflow.complete_paypal_order', CompletePayPalOrderListener::class)
        ->args([service('sylius_paypal.processor.paypal_order_complete')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_order_checkout.completed.complete', 'priority' => 100]);

    $services->set('sylius_paypal.listener.workflow.refund_payment', RefundPaymentListener::class)
        ->args([service('sylius_paypal.processor.payment_refund')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.transition.refund', 'priority' => 100]);
};
