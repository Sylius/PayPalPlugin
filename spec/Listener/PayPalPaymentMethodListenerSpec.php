<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Listener;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalPaymentMethodListenerSpec extends ObjectBehavior
{
    function let(
        OnboardingInitiatorInterface $onboardingInitiator,
        UrlGeneratorInterface $urlGenerator,
        FlashBagInterface $flashBag,
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider
    ): void {
        $this->beConstructedWith(
            $onboardingInitiator,
            $urlGenerator,
            $flashBag,
            $payPalPaymentMethodProvider
        );
    }

    function it_initiates_onboarding_when_creating_a_supported_payment_method(
        OnboardingInitiatorInterface $onboardingInitiator,
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        ResourceControllerEvent $event,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $event->getSubject()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $payPalPaymentMethodProvider->provide()->willThrow(PayPalPaymentMethodNotFoundException::class);

        $onboardingInitiator->supports($paymentMethod)->willReturn(true);

        $onboardingInitiator->initiate($paymentMethod)->willReturn('https://example.com/onboarding-url');

        $this->initializeCreate($event);

        $event->setResponse(Argument::that(static function ($argument): bool {
            return $argument instanceof RedirectResponse && $argument->getTargetUrl() === 'https://example.com/onboarding-url';
        }))->shouldHaveBeenCalled();

        $this->initializeCreate($event);
    }

    function it_throws_an_exception_if_subject_is_not_a_payment_method(ResourceControllerEvent $event): void
    {
        $event->getSubject()->willReturn(new \stdClass());

        $this->shouldThrow(\InvalidArgumentException::class)->during('initializeCreate', [$event]);
    }

    function it_redirects_with_error_if_the_pay_pal_payment_method_already_exists(
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        OnboardingInitiatorInterface $onboardingInitiator,
        UrlGeneratorInterface $urlGenerator,
        FlashBagInterface $flashBag,
        ResourceControllerEvent $event,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $event->getSubject()->willReturn($paymentMethod);
        $payPalPaymentMethodProvider->provide()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $flashBag->add('error', 'sylius.pay_pal.more_than_one_seller_not_allowed')->shouldBeCalled();

        $urlGenerator->generate('sylius_admin_payment_method_index')->willReturn('http://redirect-url.com');
        $event->setResponse(Argument::that(function (RedirectResponse $response): bool {
            return $response->getTargetUrl() === 'http://redirect-url.com';
        }))->shouldBeCalled();

        $onboardingInitiator->initiate(Argument::any())->shouldNotBeCalled();

        $this->initializeCreate($event);
    }

    function it_does_nothing_when_creating_an_unsupported_payment_method(
        OnboardingInitiatorInterface $onboardingInitiator,
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        ResourceControllerEvent $event,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $event->getSubject()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $payPalPaymentMethodProvider->provide()->willThrow(PayPalPaymentMethodNotFoundException::class);

        $onboardingInitiator->supports($paymentMethod)->willReturn(false);

        $event->setResponse(Argument::any())->shouldNotHaveBeenCalled();

        $this->initializeCreate($event);
    }

    function it_does_nothing_if_payment_method_is_not_pay_pal(
        ResourceControllerEvent $event,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $event->getSubject()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('offline');

        $event->setResponse(Argument::any())->shouldNotBeCalled();

        $this->initializeCreate($event);
    }
}
