<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Listener;

use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class PayPalPaymentMethodListener
{
    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function initializeCreate(ResourceControllerEvent $event): void
    {
        $paymentMethod = $event->getSubject();

        /** @var PaymentMethodInterface $paymentMethod */
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        $factoryName = $paymentMethod->getGatewayConfig()->getFactoryName();

        if ($factoryName !== 'sylius.pay_pal') {
            return;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig()->getConfig();

        if (isset($gatewayConfig['merchant_id'])) {
            return;
        }

        // TODO: POST partner referrals, redirect to PayPal, use the link below as a redirection url

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate(
            'sylius_admin_payment_method_create',
            [
                'factory' => $factoryName,
                'merchantId' => 'MERCHANT-ID',
                'merchantIdInPayPal' => 'MERCHANT-ID-PAYPAL',
            ]
        )));
    }
}
