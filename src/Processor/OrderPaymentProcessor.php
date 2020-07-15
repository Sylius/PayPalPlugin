<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class OrderPaymentProcessor implements OrderProcessorInterface
{
    /** @var OrderProcessorInterface */
    private $baseOrderPaymentProcessor;

    public function __construct(OrderProcessorInterface $baseOrderPaymentProcessor)
    {
        $this->baseOrderPaymentProcessor = $baseOrderPaymentProcessor;
    }

    public function process(OrderInterface $order): void
    {
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        if (
            $payment !== null &&
            $payment->getDetails()['status'] === 'CAPTURED'
        ) {
            return;
        }

        $this->baseOrderPaymentProcessor->process($order);
    }
}
