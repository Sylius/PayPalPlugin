<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Listener;

use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class InitializeCreatePayPalPaymentMethodListener
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $paymentMethod = $event->getSubject();

        /** @var PaymentMethodInterface $paymentMethod */
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        $factoryName = $paymentMethod->getGatewayConfig()->getFactoryName();

        $gatewayConfig = $paymentMethod->getGatewayConfig()->getConfig();
        
        if ($factoryName === 'sylius.pay_pal' && !isset($gatewayConfig['merchant_id'])) {
            // redirect to PayPal

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
}
