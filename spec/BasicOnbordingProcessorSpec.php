<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin;

use Payum\Core\Model\GatewayConfig;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\PayPalPlugin\OnboardingProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

final class BasicOnbordingProcessorSpec extends ObjectBehavior
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
        $request->query->set('merchantId', 'MERCHANT-ID');
        $request->query->set('merchantIdInPayPal', 'MERCHANT-ID-PAYPAL');

        $this->process($paymentMethod, $request)->getGatewayConfig()->getConfig()->shouldReturn([
            'merchant_id' => 'MERCHANT-ID',
            'merchant_id_in_paypal' => 'MERCHANT-ID-PAYPAL',
        ]);
    }

    function it_throws_an_exception_when_trying_to_process_onboarding_for_unsupported_payment_method_or_request(): void
    {
        $this->shouldThrow(\DomainException::class)->during('process', [new PaymentMethod(), new Request()]);
    }

    function it_supports_paypal_payment_method_with_request_containing_merchant_id(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius.pay_pal');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $request = new Request();
        $request->query->set('merchantId', 'MERCHANT-ID');

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

    function it_does_not_support_payment_method_that_has_merchant_id_is_not_set_on_request(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius.pay_pal');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->supports($paymentMethod, new Request())->shouldReturn(false);
    }
}
