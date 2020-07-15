<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Resolver;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

final class PayPalPrioritisingPaymentMethodsResolverSpec extends ObjectBehavior
{
    function let(PaymentMethodsResolverInterface $paymentMethodsResolver): void
    {
        $this->beConstructedWith($paymentMethodsResolver, 'prioritised');
    }

    function it_implements_payment_methods_resolver_interface(): void
    {
        $this->shouldImplement(PaymentMethodsResolverInterface::class);
    }

    function it_prioritizes_payment_method(
        BasePaymentInterface $payment,
        PaymentMethodsResolverInterface $paymentMethodsResolver,
        PaymentMethodInterface $firstPayment,
        PaymentMethodInterface $secondPayment,
        PaymentMethodInterface $thirdPayment,
        GatewayConfigInterface $firstGatewayConfig,
        GatewayConfigInterface $secondGatewayConfig,
        GatewayConfigInterface $thirdGatewayConfig
    ): void {
        $firstPayment->getGatewayConfig()->willReturn($firstGatewayConfig);
        $firstGatewayConfig->getFactoryName()->willReturn('payment1');

        $secondPayment->getGatewayConfig()->willReturn($secondGatewayConfig);
        $secondGatewayConfig->getFactoryName()->willReturn('payment2');

        $thirdPayment->getGatewayConfig()->willReturn($thirdGatewayConfig);
        $thirdGatewayConfig->getFactoryName()->willReturn('prioritised');

        $paymentMethodsResolver->getSupportedMethods($payment)->willReturn([$firstPayment, $secondPayment, $thirdPayment]);

        $this->getSupportedMethods($payment)->shouldReturn(
            [$thirdPayment, $firstPayment, $secondPayment]
        );
    }

    function it_does_nothing_if_prioritized_payment_is_not_available(
        BasePaymentInterface $payment,
        PaymentMethodsResolverInterface $paymentMethodsResolver,
        PaymentMethodInterface $firstPayment,
        PaymentMethodInterface $secondPayment,
        PaymentMethodInterface $thirdPayment,
        GatewayConfigInterface $firstGatewayConfig,
        GatewayConfigInterface $secondGatewayConfig,
        GatewayConfigInterface $thirdGatewayConfig
    ): void {
        $firstPayment->getGatewayConfig()->willReturn($firstGatewayConfig);
        $firstGatewayConfig->getFactoryName()->willReturn('payment1');

        $secondPayment->getGatewayConfig()->willReturn($secondGatewayConfig);
        $secondGatewayConfig->getFactoryName()->willReturn('payment2');

        $thirdPayment->getGatewayConfig()->willReturn($thirdGatewayConfig);
        $thirdGatewayConfig->getFactoryName()->willReturn('payment3');

        $paymentMethodsResolver->getSupportedMethods($payment)->willReturn([$firstPayment, $secondPayment, $thirdPayment]);

        $this->getSupportedMethods($payment)->shouldReturn(
            [$firstPayment, $secondPayment, $thirdPayment]
        );
    }
}
