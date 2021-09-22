<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Resolver;

use Payum\Core\Payum;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;

final class CompleteOrderPaymentResolver implements CompleteOrderPaymentResolverInterface
{
    private Payum $payum;

    public function __construct(Payum $payum)
    {
        $this->payum = $payum;
    }

    public function resolve(PaymentInterface $payment, string $payPalOrderId): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $this
            ->payum
            ->getGateway($gatewayConfig->getGatewayName())
            ->execute(new CompleteOrder($payment, $payPalOrderId))
        ;
    }
}
