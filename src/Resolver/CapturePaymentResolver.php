<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Resolver;

use Payum\Core\Payum;
use Payum\Core\Request\Capture;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class CapturePaymentResolver implements CapturePaymentResolverInterface
{
    private Payum $payum;

    public function __construct(Payum $payum)
    {
        $this->payum = $payum;
    }

    public function resolve(PaymentInterface $payment): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $this
            ->payum
            ->getGateway($gatewayConfig->getGatewayName())
            ->execute(new Capture($payment))
        ;
    }
}
