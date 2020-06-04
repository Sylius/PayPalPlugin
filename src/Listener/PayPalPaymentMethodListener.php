<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Listener;

use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\OnboardingInitiatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

        $event->setResponse(new RedirectResponse($this->onboardingInitiator->initiate($paymentMethod)));
    }
}
