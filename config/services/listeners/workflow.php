<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\PayPalPlugin\EventListener\Workflow\CompleteCaptureAuthorizationPaymentRequestsListener;
use Sylius\PayPalPlugin\EventListener\Workflow\PaymentCompleteGuardListener;
use Sylius\PayPalPlugin\EventListener\Workflow\PaymentRefundGuardListener;
use Sylius\PayPalPlugin\EventListener\Workflow\VoidCancelledAuthorizationListener;
use Sylius\PayPalPlugin\PackageTracking\EventListener\Workflow\SendShipmentTrackingListener;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_paypal.listener.workflow.payment_complete_guard', PaymentCompleteGuardListener::class)
        ->args([service('sylius_paypal.checker.payment_completion')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.guard.complete']);

    $services->set('sylius_paypal.listener.workflow.complete_capture_authorization_payment_requests', CompleteCaptureAuthorizationPaymentRequestsListener::class)
        ->args([service('sylius_paypal.completer.capture_authorization_payment_request')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.complete']);

    $services->set('sylius_paypal.listener.workflow.payment_refund_guard', PaymentRefundGuardListener::class)
        ->args([service('sylius_paypal.checker.payment_refund')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.guard.refund']);

    $services->set('sylius_paypal.listener.workflow.void_cancelled_authorization', VoidCancelledAuthorizationListener::class)
        ->public()
        ->args([
            service('sylius_paypal.dispatcher.void_authorization_payment_request'),
            service('monolog.logger.paypal'),
        ])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.cancel']);

    $services->set('sylius_paypal.listener.workflow.send_shipment_tracking', SendShipmentTrackingListener::class)
        ->args([service('sylius_paypal.dispatcher.shipment_tracking')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_shipment.completed.ship', 'priority' => 100]);
};
