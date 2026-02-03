<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Listener;

use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Webmozart\Assert\Assert;

final readonly class PayPalPaymentMethodListener
{
    public function __construct(
        private OnboardingInitiatorInterface $onboardingInitiator,
        private bool $isSandbox = false,
    ) {
    }

    public function initializeCreate(ResourceControllerEvent $event): void
    {
        /** @var PaymentMethodInterface|mixed $paymentMethod */
        $paymentMethod = $event->getSubject();
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        if (!$this->isNewPaymentMethodPayPal($paymentMethod)) {
            return;
        }

        if ($this->isSandbox || !$this->onboardingInitiator->supports($paymentMethod)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->onboardingInitiator->initiate($paymentMethod)));
    }

    private function isNewPaymentMethodPayPal(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME;
    }
}
