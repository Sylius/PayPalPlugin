<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingProcessorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PayPalPaymentMethodNewResourceFactory implements NewResourceFactoryInterface
{
    /** @var NewResourceFactoryInterface */
    private $newResourceFactory;

    /** @var OnboardingProcessorInterface */
    private $onboardingProcessor;

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string */
    private $url;

    public function __construct(
        NewResourceFactoryInterface $newResourceFactory,
        OnboardingProcessorInterface $onboardingProcessor,
        HttpClientInterface $httpClient,
        string $url
    ) {
        $this->newResourceFactory = $newResourceFactory;
        $this->onboardingProcessor = $onboardingProcessor;
        $this->httpClient = $httpClient;
        $this->url = $url;
    }

    public function create(RequestConfiguration $requestConfiguration, FactoryInterface $factory): ResourceInterface
    {
        $resource = $this->newResourceFactory->create($requestConfiguration, $factory);

        if (!$resource instanceof PaymentMethodInterface) {
            return $resource;
        }

        $request = $requestConfiguration->getRequest();

        if ($this->onboardingProcessor->supports($resource, $request)) {
            return $this->onboardingProcessor->process($resource, $request, $this->httpClient, $this->url);
        }

        return $resource;
    }
}
