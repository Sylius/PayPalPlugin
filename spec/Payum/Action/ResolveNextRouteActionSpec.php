<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\PayumBundle\Request\ResolveNextRoute;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class ResolveNextRouteActionSpec extends ObjectBehavior
{
    function it_executes_resolve_next_route_request_with_processing_payment(
        ResolveNextRoute $request,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $request->getFirstModel()->willReturn($payment);

        $payment->getState()->willReturn(PaymentInterface::STATE_NEW);
        $payment->getId()->willReturn(12);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $payment->getOrder()->willReturn($order);
        $order->getTokenValue()->willReturn('123!@#asd');

        $request->setRouteName('sylius_paypal_plugin_pay_with_paypal_form')->shouldBeCalled();
        $request->setRouteParameters(['orderToken' => '123!@#asd', 'paymentId' => 12])->shouldBeCalled();

        $this->execute($request);
    }

    function it_executes_resolve_next_route_request_with_completed_payment(
        ResolveNextRoute $request,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $request->getFirstModel()->willReturn($payment);
        $payment->getOrder()->willReturn($order);

        $payment->getState()->willReturn(PaymentInterface::STATE_COMPLETED);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $request->setRouteName('sylius_shop_order_thank_you')->shouldBeCalled();

        $this->execute($request);
    }

    function it_executes_resolve_next_route_request_with_some_other_payment(
        ResolveNextRoute $request,
        PaymentInterface $payment,
        OrderInterface $order,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $request->getFirstModel()->willReturn($payment);

        $payment->getState()->willReturn(PaymentInterface::STATE_FAILED);
        $payment->getOrder()->willReturn($order);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $order->getTokenValue()->willReturn('TOKEN_VALUE');

        $request->setRouteName('sylius_shop_order_show')->shouldBeCalled();
        $request->setRouteParameters(['tokenValue' => 'TOKEN_VALUE'])->shouldBeCalled();

        $this->execute($request);
    }

    function it_supports_resolve_next_route_request_with_payment_as_first_model(
        ResolveNextRoute $request,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $request->getFirstModel()->willReturn($payment);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $this->supports($request)->shouldReturn(true);
    }

    function it_does_not_support_payment_with_other_factory_name_than_pay_pal(
        ResolveNextRoute $request,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $request->getFirstModel()->willReturn($payment);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('offline');

        $this->supports($request)->shouldReturn(false);
    }

    function it_does_not_support_request_other_than_resolve_next_route(Capture $request): void
    {
        $this->supports($request)->shouldReturn(false);
    }

    function it_does_not_support_request_with_first_model_other_than_payment(ResolveNextRoute $request): void
    {
        $request->getFirstModel()->willReturn('badObject');

        $this->supports($request)->shouldReturn(false);
    }
}
