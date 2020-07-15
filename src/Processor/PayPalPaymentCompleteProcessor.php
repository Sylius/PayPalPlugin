<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Payum\Core\Payum;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;

final class PayPalPaymentCompleteProcessor
{
    /** @var Payum */
    private $payum;

    /** @var PaymentStateManagerInterface */
    private $paymentStateManager;

    public function __construct(Payum $payum, PaymentStateManagerInterface $paymentStateManager)
    {
        $this->payum = $payum;
        $this->paymentStateManager = $paymentStateManager;
    }

    public function completePayPalOrder(OrderInterface $order): void
    {
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        if ($payment === null) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig->getGatewayName() !== 'paypal') {
            return;
        }

        $this
            ->payum
            ->getGateway($gatewayConfig->getGatewayName())
            ->execute(new CompleteOrder($payment, (string) $payment->getDetails()['paypal_order_id']))
        ;

        $this->paymentStateManager->complete($payment);
    }
}
