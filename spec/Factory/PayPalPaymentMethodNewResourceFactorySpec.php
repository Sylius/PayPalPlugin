<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Factory;

use PhpSpec\ObjectBehavior;
use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

final class PayPalPaymentMethodNewResourceFactorySpec extends ObjectBehavior
{
    function let(
        NewResourceFactoryInterface $newResourceFactory,
        OnboardingProcessorInterface $onboardingProcessor,
    ): void {
        $this->beConstructedWith($newResourceFactory, $onboardingProcessor);
    }

    function it_is_a_new_resource_factory(): void
    {
        $this->shouldImplement(NewResourceFactoryInterface::class);
    }

    function it_processes_onboarding_if_payment_method_and_request_are_supported(
        NewResourceFactoryInterface $newResourceFactory,
        OnboardingProcessorInterface $onboardingProcessor,
        RequestConfiguration $requestConfiguration,
        Request $request,
        FactoryInterface $factory,
        PaymentMethodInterface $paymentMethod,
        PaymentMethodInterface $processedPaymentMethod,
    ): void {
        $newResourceFactory->create($requestConfiguration, $factory)->willReturn($paymentMethod);

        $requestConfiguration->getRequest()->willReturn($request);

        $onboardingProcessor->supports($paymentMethod, $request)->willReturn(true);
        $onboardingProcessor->process($paymentMethod, $request)->willReturn($processedPaymentMethod);

        $this->create($requestConfiguration, $factory)->shouldReturn($processedPaymentMethod);
    }

    function it_does_nothing_if_payment_method_and_request_are_unsupported(
        NewResourceFactoryInterface $newResourceFactory,
        OnboardingProcessorInterface $onboardingProcessor,
        RequestConfiguration $requestConfiguration,
        Request $request,
        FactoryInterface $factory,
        PaymentMethodInterface $paymentMethod,
    ): void {
        $newResourceFactory->create($requestConfiguration, $factory)->willReturn($paymentMethod);

        $requestConfiguration->getRequest()->willReturn($request);

        $onboardingProcessor->supports($paymentMethod, $request)->willReturn(false);

        $this->create($requestConfiguration, $factory)->shouldReturn($paymentMethod);

        $onboardingProcessor->process($paymentMethod, $request)->shouldNotHaveBeenCalled();
    }

    function it_does_nothing_if_created_resource_is_not_a_payment_method(
        NewResourceFactoryInterface $newResourceFactory,
        RequestConfiguration $requestConfiguration,
        FactoryInterface $factory,
        ResourceInterface $resource,
    ): void {
        $newResourceFactory->create($requestConfiguration, $factory)->willReturn($resource);

        $this->create($requestConfiguration, $factory)->shouldReturn($resource);
    }
}
