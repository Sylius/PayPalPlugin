<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\PayumBundle\Request\ResolveNextRoute;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class ResolveNextRouteActionSpec extends ObjectBehavior
{
    function it_executes_resolve_next_route_request_with_processing_payment(
        ResolveNextRoute $request,
        PaymentInterface $payment
    ): void {
        $request->getFirstModel()->willReturn($payment);

        $payment->getState()->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->getId()->willReturn(12);

        $request->setRouteName('sylius_paypal_plugin_pay_with_paypal_form')->shouldBeCalled();
        $request->setRouteParameters(['id' => 12])->shouldBeCalled();

        $this->execute($request);
    }

    function it_executes_resolve_next_route_request_with_completed_payment(
        ResolveNextRoute $request,
        PaymentInterface $payment
    ): void {
        $request->getFirstModel()->willReturn($payment);

        $payment->getState()->willReturn(PaymentInterface::STATE_COMPLETED);

        $request->setRouteName('sylius_shop_order_thank_you')->shouldBeCalled();

        $this->execute($request);
    }

    function it_executes_resolve_next_route_request_with_some_other_payment(
        ResolveNextRoute $request,
        PaymentInterface $payment,
        OrderInterface $order
    ): void {
        $request->getFirstModel()->willReturn($payment);

        $payment->getState()->willReturn(PaymentInterface::STATE_FAILED);
        $payment->getOrder()->willReturn($order);
        $order->getTokenValue()->willReturn('TOKEN_VALUE');

        $request->setRouteName('sylius_shop_order_show')->shouldBeCalled();
        $request->setRouteParameters(['tokenValue' => 'TOKEN_VALUE'])->shouldBeCalled();

        $this->execute($request);
    }

    function it_supports_resolve_next_route_request_with_payment_as_first_model(
        ResolveNextRoute $request,
        PaymentInterface $payment
    ): void {
        $request->getFirstModel()->willReturn($payment);

        $this->supports($request)->shouldReturn(true);
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
