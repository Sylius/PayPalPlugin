<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Resolver;

use Payum\Core\GatewayInterface;
use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Payum;
use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;

final class CapturePaymentResolverSpec extends ObjectBehavior
{
    function let(Payum $payum): void
    {
        $this->beConstructedWith($payum);
    }

    function it_is_an_capture_payment_resolver(): void
    {
        $this->shouldImplement(CapturePaymentResolverInterface::class);
    }

    function it_executes_capture_action_on_payment(
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

        $gateway->execute(Argument::that(function (Capture $request) use ($payment): bool {
            return $request->getModel() === $payment->getWrappedObject();
        }))->shouldBeCalled();

        $this->resolve($payment);
    }
}
