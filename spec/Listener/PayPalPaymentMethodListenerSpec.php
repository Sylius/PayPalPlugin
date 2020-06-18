<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Listener;

use Payum\Core\Model\GatewayConfig;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class PayPalPaymentMethodListenerSpec extends ObjectBehavior
{
    function let(OnboardingInitiatorInterface $onboardingInitiator): void
    {
        $this->beConstructedWith($onboardingInitiator);
    }

    function it_initiates_onboarding_when_creating_a_supported_payment_method(
        OnboardingInitiatorInterface $onboardingInitiator,
        ResourceControllerEvent $event,
        PaymentMethodInterface $paymentMethod,
        GatewayConfig $config
    ): void {
        $event->getSubject()->willReturn($paymentMethod);
        $event->getErrorCode()->willReturn('200');

        $paymentMethod->getGatewayConfig()->willReturn($config);
        $config->getConfig()->willReturn(
            [
                'request_method' => 'GET',
            ]
        );

        $onboardingInitiator->supports($paymentMethod)->willReturn(true);

        $onboardingInitiator->initiate($paymentMethod)->willReturn('https://example.com/onboarding-url');

        $this->initializeCreate($event);

        $event->setResponse(Argument::that(static function ($argument): bool {
            return $argument instanceof RedirectResponse && $argument->getTargetUrl() === 'https://example.com/onboarding-url';
        }))->shouldHaveBeenCalled();
    }

    function it_redirects_back_if_form_returns_error(
        OnboardingInitiatorInterface $onboardingInitiator,
        ResourceControllerEvent $event,
        PaymentMethodInterface $paymentMethod,
        GatewayConfig $config
    ): void {
        $event->getSubject()->willReturn($paymentMethod);
        $event->getErrorCode()->willReturn('500');

        $paymentMethod->getGatewayConfig()->willReturn($config);
        $config->getConfig()->willReturn(
            [
                'request_method' => 'POST',
            ]
        );

        $onboardingInitiator->supports($paymentMethod)->willReturn(true);

        $onboardingInitiator->initiate($paymentMethod)->willReturn('https://example.com/onboarding-url');

        $this->initializeCreate($event);

        $event->setResponse(Argument::that(static function ($argument): bool {
            return $argument instanceof RedirectResponse && $argument->getTargetUrl() === 'https://example.com/onboarding-url';
        }))->shouldNotBeCalled();
    }

    function it_throws_an_exception_if_subject_is_not_a_payment_method(ResourceControllerEvent $event): void
    {
        $event->getSubject()->willReturn(new \stdClass());

        $this->shouldThrow(\InvalidArgumentException::class)->during('initializeCreate', [$event]);
    }

    function it_does_nothing_when_creating_an_unsupported_payment_method(
        OnboardingInitiatorInterface $onboardingInitiator,
        ResourceControllerEvent $event,
        PaymentMethodInterface $paymentMethod
    ): void {
        $event->getSubject()->willReturn($paymentMethod);

        $onboardingInitiator->supports($paymentMethod)->willReturn(false);

        $this->initializeCreate($event);

        $event->setResponse(Argument::any())->shouldNotHaveBeenCalled();
    }
}
