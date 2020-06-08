<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Onboarding\Processor;

use Payum\Core\Model\GatewayConfig;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

final class BasicOnboardingProcessorSpec extends ObjectBehavior
{
    function it_is_an_onboarding_processor(): void
    {
        $this->shouldImplement(OnboardingProcessorInterface::class);
    }

    function it_processes_onboarding_for_supported_payment_method_and_request(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius.pay_pal');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $request = new Request();
        $request->query->set('clientId', 'CLIENT-ID');
        $request->query->set('clientSecret', 'CLIENT-SECRET');

        $config = $this->process($paymentMethod, $request)->getGatewayConfig()->getConfig();
        $config->shouldHaveKeyWithValue('client_id', 'CLIENT-ID');
        $config->shouldHaveKeyWithValue('client_secret', 'CLIENT-SECRET');
    }

    function it_throws_an_exception_when_trying_to_process_onboarding_for_unsupported_payment_method_or_request(): void
    {
        $this->shouldThrow(\DomainException::class)->during('process', [new PaymentMethod(), new Request()]);
    }

    function it_supports_paypal_payment_method_with_request_containing_client_id_and_secret(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius.pay_pal');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $request = new Request();
        $request->query->set('clientId', 'CLIENT-ID');
        $request->query->set('clientSecret', 'CLIENT-SECRET');

        $this->supports($paymentMethod, $request)->shouldReturn(true);
    }

    function it_does_not_support_payment_method_that_has_no_gateway_config(): void
    {
        $this->supports(new PaymentMethod(), new Request())->shouldReturn(false);
    }

    function it_does_not_support_payment_method_that_does_not_have_paypal_as_a_gateway_factory(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('random');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->supports($paymentMethod, new Request())->shouldReturn(false);
    }

    function it_does_not_support_payment_method_that_has_client_id_is_not_set_on_request(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius.pay_pal');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->supports($paymentMethod, new Request())->shouldReturn(false);
    }
}
