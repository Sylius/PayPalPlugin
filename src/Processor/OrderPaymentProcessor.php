<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

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
        Assert::isInstanceOf($order, \Sylius\Component\Core\Model\OrderInterface::class);

        /** @var PaymentInterface|null $payment */
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
