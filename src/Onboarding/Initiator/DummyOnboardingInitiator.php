<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Initiator;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DummyOnboardingInitiator implements OnboardingInitiatorInterface
{
    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function initiate(PaymentMethodInterface $paymentMethod): string
    {
        if (!$this->supports($paymentMethod)) {
            throw new \DomainException('not supported'); // TODO: Lol, improve this message
        }

        return $this->urlGenerator->generate(
            'sylius_admin_payment_method_create',
            [
                'factory' => 'sylius.pay_pal',
                'clientId' => 'CLIENT-ID',
                'clientSecret' => 'CLIENT-SECRET',
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool // TODO: Design smell - it looks like this function will be the same no matter the implementation
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig === null) {
            return false;
        }

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return false;
        }

        if (isset($gatewayConfig->getConfig()['client_id'])) {
            return false;
        }

        return true;
    }
}
