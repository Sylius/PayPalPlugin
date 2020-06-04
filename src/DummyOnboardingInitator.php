<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DummyOnboardingInitator implements OnboardingInitiatorInterface
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
            throw new \DomainException('not supported');
        }

        return $this->urlGenerator->generate(
            'sylius_admin_payment_method_create',
            [
                'factory' => 'sylius.pay_pal',
                'merchantId' => 'MERCHANT-ID',
                'merchantIdInPayPal' => 'MERCHANT-ID-PAYPAL',
            ]
        );
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig === null) {
            return false;
        }

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return false;
        }

        if (isset($gatewayConfig->getConfig()['merchant_id'])) {
            return false;
        }

        return true;
    }
}
