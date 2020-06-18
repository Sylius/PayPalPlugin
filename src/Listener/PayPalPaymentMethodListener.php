<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Listener;

use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class PayPalPaymentMethodListener
{
    /** @var OnboardingInitiatorInterface */
    private $onboardingInitiator;

    public function __construct(OnboardingInitiatorInterface $onboardingInitiator)
    {
        $this->onboardingInitiator = $onboardingInitiator;
    }

    public function initializeCreate(ResourceControllerEvent $event): void
    {
        $paymentMethod = $event->getSubject();

        /** @var PaymentMethodInterface $paymentMethod */
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        if (!$this->onboardingInitiator->supports($paymentMethod)) {
            return;
        }

        /** @var GatewayConfig $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if (
            isset($gatewayConfig->getConfig()['request_method']) &&
            $gatewayConfig->getConfig()['request_method'] === 'POST' &&
            $event->getErrorCode() !== Response::HTTP_OK
        ) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->onboardingInitiator->initiate($paymentMethod)));
    }
}
