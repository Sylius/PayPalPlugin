<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Listener;

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;
use Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class PayPalPaymentMethodListener
{
    private OnboardingInitiatorInterface $onboardingInitiator;

    private UrlGeneratorInterface $urlGenerator;

    private FlashBagInterface|RequestStack $flashBagOrRequestStack;

    private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider;

    public function __construct(
        OnboardingInitiatorInterface $onboardingInitiator,
        UrlGeneratorInterface $urlGenerator,
        FlashBagInterface|RequestStack $flashBagOrRequestStack,
        PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider
    ) {
        if ($flashBagOrRequestStack instanceof FlashBagInterface) {
            trigger_deprecation('sylius/paypal-plugin', '1.5', sprintf('Passing an instance of %s as constructor argument for %s is deprecated as of PayPalPlugin 1.5 and will be removed in 2.0. Pass an instance of %s instead.', FlashBagInterface::class, self::class, RequestStack::class));
        }

        $this->onboardingInitiator = $onboardingInitiator;
        $this->urlGenerator = $urlGenerator;
        $this->flashBagOrRequestStack = $flashBagOrRequestStack;
        $this->payPalPaymentMethodProvider = $payPalPaymentMethodProvider;
    }

    public function initializeCreate(ResourceControllerEvent $event): void
    {
        /** @var object $paymentMethod */
        $paymentMethod = $event->getSubject();
        /** @var PaymentMethodInterface $paymentMethod */
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        if (!$this->isNewPaymentMethodPayPal($paymentMethod)) {
            return;
        }

        if ($this->isTherePayPalPaymentMethod()) {
            FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)
                ->add('error', 'sylius.pay_pal.more_than_one_seller_not_allowed')
            ;

            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('sylius_admin_payment_method_index')));

            return;
        }

        if (!$this->onboardingInitiator->supports($paymentMethod)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->onboardingInitiator->initiate($paymentMethod)));
    }

    private function isNewPaymentMethodPayPal(PaymentMethodInterface $paymentMethod): bool
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig->getFactoryName() === 'sylius.pay_pal';
    }

    private function isTherePayPalPaymentMethod(): bool
    {
        try {
            $this->payPalPaymentMethodProvider->provide();
        } catch (PayPalPaymentMethodNotFoundException $exception) {
            return false;
        }

        return true;
    }
}
