<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Onboarding\Initiator;

use Payum\Core\Model\GatewayConfig;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DummyOnboardingInitatorSpec extends ObjectBehavior
{
    function let(UrlGeneratorInterface $urlGenerator): void
    {
        $this->beConstructedWith($urlGenerator);
    }

    function it_is_an_onboarding_initiator(): void
    {
        $this->shouldImplement(OnboardingInitiatorInterface::class);
    }

    function it_initiates_onboarding_for_supported_new_paypal_payment_methods(UrlGeneratorInterface $urlGenerator): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius.pay_pal');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $urlGenerator->generate(Argument::cetera())->willReturn('https://example.com/onboarding-url');

        $this->initiate($paymentMethod)->shouldReturn('https://example.com/onboarding-url');
    }

    function it_throws_an_exception_when_trying_to_initiate_onboarding_for_unsupported_payment_method(): void
    {
        $this->shouldThrow(\DomainException::class)->during('initiate', [new PaymentMethod()]);
    }

    function it_supports_new_paypal_payment_methods(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius.pay_pal');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->supports($paymentMethod)->shouldReturn(true);
    }

    function it_does_not_support_payment_method_that_has_no_gateway_config(): void
    {
        $this->supports(new PaymentMethod())->shouldReturn(false);
    }

    function it_does_not_support_payment_method_that_does_not_have_paypal_as_a_gateway_factory(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('random');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->supports($paymentMethod)->shouldReturn(false);
    }

    function it_does_not_support_payment_method_that_has_merchant_id_set_in_gateway_config(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius.pay_pal');
        $gatewayConfig->setConfig(['merchant_id' => 'MERCHANT-ID']);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->supports($paymentMethod)->shouldReturn(false);
    }
}
