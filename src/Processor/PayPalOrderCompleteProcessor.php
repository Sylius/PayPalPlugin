<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;

final class PayPalOrderCompleteProcessor
{
    private PaymentStateManagerInterface $paymentStateManager;

    public function __construct(PaymentStateManagerInterface $paymentStateManager)
    {
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

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return;
        }

        $this->paymentStateManager->complete($payment);
    }
}
