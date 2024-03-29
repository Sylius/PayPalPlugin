<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use SM\Factory\FactoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Abstraction\StateMachine\WinzouStateMachineAdapter;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Webmozart\Assert\Assert;

final class OrderPaymentProcessor implements OrderProcessorInterface
{
    public function __construct(
        private readonly OrderProcessorInterface $baseOrderPaymentProcessor,
        private readonly FactoryInterface|StateMachineInterface $stateMachineFactory,
    ) {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                sprintf(
                    'Passing an instance of "%s" as the second argument is deprecated and will be prohibited in 2.0. Use "%s" instead.',
                    FactoryInterface::class,
                    StateMachineInterface::class,
                ),
            );
        }
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
            $this->getStateMachine()->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
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

    private function getStateMachine(): StateMachineInterface
    {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            return new WinzouStateMachineAdapter($this->stateMachineFactory);
        }

        return $this->stateMachineFactory;
    }
}
