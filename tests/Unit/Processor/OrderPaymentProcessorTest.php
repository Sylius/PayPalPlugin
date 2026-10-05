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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Processor\OrderPaymentProcessor;

final class OrderPaymentProcessorTest extends TestCase
{
    private OrderPaymentProcessor $orderPaymentProcessor;

    private OrderProcessorInterface&MockObject $baseOrderProcessor;

    private StateMachineInterface&MockObject $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseOrderProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);

        $this->orderPaymentProcessor = new OrderPaymentProcessor(
            $this->baseOrderProcessor,
            $this->stateMachine,
        );
    }

    #[Test]
    public function it_implements_order_processor_interface(): void
    {
        self::assertInstanceOf(OrderProcessorInterface::class, $this->orderPaymentProcessor);
    }

    #[Test]
    public function it_does_nothing_if_there_is_a_paypal_processing_captured_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn($payment);
        $payment->method('getDetails')->willReturn(['status' => 'CAPTURED']);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');

        $this->baseOrderProcessor->expects($this->never())->method('process');

        $this->orderPaymentProcessor->process($order);
    }

    #[Test]
    public function it_processes_order_if_there_is_no_processing_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn(null);

        $this->baseOrderProcessor->expects(self::once())->method('process')->with($order);

        $this->orderPaymentProcessor->process($order);
    }

    #[Test]
    public function it_processes_order_if_the_processing_payment_is_not_captured(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn($payment);
        $payment->method('getDetails')->willReturn(['status' => 'CANCELLED']);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');

        $this->baseOrderProcessor->expects(self::once())->method('process')->with($order);

        $this->orderPaymentProcessor->process($order);
    }

    #[Test]
    public function it_cancels_payment_and_processes_order_if_the_processing_payment_has_method_change_to_non_paypal(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn($payment);
        $payment->method('getDetails')->willReturn(['status' => 'CANCELLED']);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('offline');

        $this->stateMachine->expects(self::once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

        $this->baseOrderProcessor->expects(self::once())->method('process')->with($order);

        $this->orderPaymentProcessor->process($order);
    }
}
