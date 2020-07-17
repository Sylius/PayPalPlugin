<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Resolver;

use Payum\Core\GatewayInterface;
use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Payum;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Resolver\CompleteOrderPaymentResolverInterface;

final class CompleteOrderPaymentResolverSpec extends ObjectBehavior
{
    function let(Payum $payum): void
    {
        $this->beConstructedWith($payum);
    }

    function it_is_an_complete_order_payment_resolver(): void
    {
        $this->shouldImplement(CompleteOrderPaymentResolverInterface::class);
    }

    function it_executes_complete_order_action_on_payment(
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        GatewayInterface $gateway,
        Payum $payum
    ): void {
        $payment->getMethod()->willReturn($paymentMethod);

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getGatewayName()->willReturn('gateway-12');

        $payum->getGateway('gateway-12')->willReturn($gateway);

        $gateway->execute(
            Argument::that(function (CompleteOrder $request) use ($payment): bool {
                return
                    $request->getModel() === $payment->getWrappedObject() &&
                    $request->getOrderId() === 'paypal-order-id'
                ;
            },
        ))->shouldBeCalled();

        $this->resolve($payment, 'paypal-order-id');
    }
}
