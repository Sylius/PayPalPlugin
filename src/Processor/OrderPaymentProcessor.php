<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Webmozart\Assert\Assert;

final readonly class OrderPaymentProcessor implements OrderProcessorInterface
{
    public function __construct(
        private OrderProcessorInterface $baseOrderPaymentProcessor,
        private StateMachineInterface $stateMachineFactory,
    ) {
    }

    public function process(OrderInterface $order): void
    {
        Assert::isInstanceOf($order, \Sylius\Component\Core\Model\OrderInterface::class);

        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        if (
            $payment !== null &&
            ($payment->getDetails()['status'] ?? null) === 'CAPTURED' &&
            $this->getFactoryName($payment) === SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ) {
            return;
        }

        if (
            $payment !== null &&
            $this->getFactoryName($payment) !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ) {
            $this->stateMachineFactory->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
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
