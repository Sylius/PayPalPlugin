<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Onboarding\Initiator;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Onboarding\Api\PartnerReferralsApiInterface;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Sylius\PayPalPlugin\Provider\ApiTokenProviderInterface;

final class SandboxOnboardingInitiatorSpec extends ObjectBehavior
{
    function let(
        ApiTokenProviderInterface $apiTokenProvider,
        PartnerReferralsApiInterface $partnerReferralsApi
    ): void {
        $this->beConstructedWith($apiTokenProvider, $partnerReferralsApi);
    }

    function it_initializes_onboarding_and_returns_a_redirection_url(
        ApiTokenProviderInterface $apiTokenProvider,
        PartnerReferralsApiInterface $partnerReferralsApi,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(['some_parameter' => 'test']);

        $apiTokenProvider->getToken()->willReturn('TOKEN123');
        $partnerReferralsApi->create('TOKEN123')->willReturn('https://returnurl.com');

        $this->initiate($paymentMethod)->shouldReturn('https://returnurl.com');
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

    function it_implements_onboarding_initiator_interface(): void
    {
        $this->shouldImplement(OnboardingInitiatorInterface::class);
    }

    function it_supports_paypal_payment_method_without_merchant_id_set(
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(['some_parameter' => 'test']);

        $this->supports($paymentMethod)->shouldReturn(true);
    }

    function it_does_not_support_paypal_payment_method_with_merchant_id_set(
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(['merchant_id' => '123123']);

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
