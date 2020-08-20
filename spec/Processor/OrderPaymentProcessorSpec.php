<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Processor;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;

final class OrderPaymentProcessorSpec extends ObjectBehavior
{
    function let(OrderProcessorInterface $baseOrderProcessor, FactoryInterface $stateMachineFactory): void
    {
        $this->beConstructedWith($baseOrderProcessor, $stateMachineFactory);
    }

    function it_implements_order_processor_interface(): void
    {
        $this->shouldImplement(OrderProcessorInterface::class);
    }

    function it_does_nothing_if_there_is_a_pay_pal_processing_captured_payment(
        OrderProcessorInterface $baseOrderProcessor,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);
        $payment->getDetails()->willReturn(['status' => 'CAPTURED']);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $baseOrderProcessor->process(Argument::any())->shouldNotBeCalled();

        $this->process($order);
    }

    function it_processes_order_if_there_is_no_processing_payment(
        OrderProcessorInterface $baseOrderProcessor,
        OrderInterface $order
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn(null);

        $baseOrderProcessor->process($order)->shouldBeCalled();

        $this->process($order);
    }

    function it_processes_order_if_the_processing_payment_is_not_captured(
        OrderProcessorInterface $baseOrderProcessor,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);
        $payment->getDetails()->willReturn(['status' => 'CANCELLED']);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $baseOrderProcessor->process($order)->shouldBeCalled();

        $this->process($order);
    }

    function it_cancels_payment_and_processes_order_if_the_processing_payment_has_method_change_to_non_pay_pal(
        OrderProcessorInterface $baseOrderProcessor,
        FactoryInterface $stateMachineFactory,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        StateMachineInterface $stateMachine
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);
        $payment->getDetails()->willReturn(['status' => 'CANCELLED']);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('offline');

        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->apply(PaymentTransitions::TRANSITION_CANCEL)->shouldBeCalled();

        $baseOrderProcessor->process($order)->shouldBeCalled();

        $this->process($order);
    }
}
