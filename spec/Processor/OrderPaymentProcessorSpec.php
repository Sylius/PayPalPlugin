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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class OrderPaymentProcessorSpec extends ObjectBehavior
{
    function let(OrderProcessorInterface $baseOrderProcessor): void
    {
        $this->beConstructedWith($baseOrderProcessor);
    }

    function it_implements_order_processor_interface(): void
    {
        $this->shouldImplement(OrderProcessorInterface::class);
    }

    function it_does_nothing_if_there_is_a_processing_captured_payment(
        OrderProcessorInterface $baseOrderProcessor,
        OrderInterface $order,
        PaymentInterface $payment
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);
        $payment->getDetails()->willReturn(['status' => 'CAPTURED']);

        $baseOrderProcessor->process(Argument::any())->shouldNotBeCalled();

        $this->process($order);
    }

    function it_processes_order_if_there_is_no_processing_payment(
        OrderProcessorInterface $baseOrderProcessor,
        OrderInterface $order,
        PaymentInterface $payment
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn(null);

        $baseOrderProcessor->process($order)->shouldBeCalled();

        $this->process($order);
    }

    function it_processes_order_if_the_processing_payment_is_not_captured(
        OrderProcessorInterface $baseOrderProcessor,
        OrderInterface $order,
        PaymentInterface $payment
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);
        $payment->getDetails()->willReturn(['status' => 'CANCELLED']);

        $baseOrderProcessor->process($order)->shouldBeCalled();

        $this->process($order);
    }
}
