<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\PayPalPlugin\EventListener\Workflow\PaymentRefundGuardListener;
use Sylius\PayPalPlugin\PackageTracking\EventListener\Workflow\SendShipmentTrackingListener;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_paypal.listener.workflow.payment_refund_guard', PaymentRefundGuardListener::class)
        ->args([service('sylius_paypal.checker.payment_refund')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.guard.refund']);

    $services->set('sylius_paypal.listener.workflow.send_shipment_tracking', SendShipmentTrackingListener::class)
        ->args([service('sylius_paypal.dispatcher.shipment_tracking')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_shipment.completed.ship', 'priority' => 100]);
};
