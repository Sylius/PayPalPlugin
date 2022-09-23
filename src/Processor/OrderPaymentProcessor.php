<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use SM\Factory\FactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Webmozart\Assert\Assert;

final class OrderPaymentProcessor implements OrderProcessorInterface
{
    private OrderProcessorInterface $baseOrderPaymentProcessor;

    private FactoryInterface $stateMachineFactory;

    public function __construct(
        OrderProcessorInterface $baseOrderPaymentProcessor,
        FactoryInterface $stateMachineFactory
    ) {
        $this->baseOrderPaymentProcessor = $baseOrderPaymentProcessor;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    public function process(OrderInterface $order): void
    {
        Assert::isInstanceOf($order, \Sylius\Component\Core\Model\OrderInterface::class);

        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        if (
            $payment !== null &&
            $payment->getDetails()['status'] === 'CAPTURED' &&
            $this->getFactoryName($payment) === 'sylius.pay_pal'
        ) {
            return;
        }

        if (
            $payment !== null &&
            $this->getFactoryName($payment) !== 'sylius.pay_pal'
        ) {
            $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
            $stateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);
        }

        $this->baseOrderPaymentProcessor->process($order);
    }

    private function getFactoryName(PaymentInterface $payment): string
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig->getFactoryName();
    }
}
