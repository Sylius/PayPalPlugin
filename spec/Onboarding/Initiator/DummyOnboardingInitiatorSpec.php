<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Onboarding\Initiator;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DummyOnboardingInitiatorSpec extends ObjectBehavior
{
    function let(UrlGeneratorInterface $urlGenerator): void
    {
        $this->beConstructedWith($urlGenerator);
    }

    function it_implements_onboarding_initiator_interface(): void
    {
        $this->shouldImplement(OnboardingInitiatorInterface::class);
    }

    function it_throws_an_exception_during_initialization_if_payment_method_is_not_supported(
        PaymentMethodInterface $paymentMethod
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn(null);

        $this
            ->shouldThrow(\DomainException::class)
            ->during('initiate', [$paymentMethod])
        ;
    }

    function it_supports_paypal_payment_method_without_client_id_set(
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(['some_parameter' => 'test']);

        $this->supports($paymentMethod)->shouldReturn(true);
    }

    function it_does_not_support_paypal_payment_method_with_client_id_set(
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(['client_id' => '123123']);

        $this->supports($paymentMethod)->shouldReturn(false);
    }

    function it_does_not_support_payment_method_with_invalid_gateway_factory_name(
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('offline');

        $this->supports($paymentMethod)->shouldReturn(false);
    }

    function it_does_not_support_payment_method_without_gateway_config(
        PaymentMethodInterface $paymentMethod
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn(null);

        $this->supports($paymentMethod)->shouldReturn(false);
    }
}
