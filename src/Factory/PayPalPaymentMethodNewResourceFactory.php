<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingProcessorInterface;

final class PayPalPaymentMethodNewResourceFactory implements NewResourceFactoryInterface
{
    private NewResourceFactoryInterface $newResourceFactory;

    private OnboardingProcessorInterface $onboardingProcessor;

    public function __construct(
        NewResourceFactoryInterface $newResourceFactory,
        OnboardingProcessorInterface $onboardingProcessor
    ) {
        $this->newResourceFactory = $newResourceFactory;
        $this->onboardingProcessor = $onboardingProcessor;
    }

    public function create(RequestConfiguration $requestConfiguration, FactoryInterface $factory): ResourceInterface
    {
        $resource = $this->newResourceFactory->create($requestConfiguration, $factory);

        if (!$resource instanceof PaymentMethodInterface) {
            return $resource;
        }

        $request = $requestConfiguration->getRequest();

        if ($this->onboardingProcessor->supports($resource, $request)) {
            return $this->onboardingProcessor->process($resource, $request);
        }

        return $resource;
    }
}
