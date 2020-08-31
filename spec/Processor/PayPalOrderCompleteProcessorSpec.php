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

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;

final class PayPalOrderCompleteProcessorSpec extends ObjectBehavior
{
    function let(PaymentStateManagerInterface $paymentStateManager): void
    {
        $this->beConstructedWith($paymentStateManager);
    }

    function it_completes_pay_pal_order(
        PaymentStateManagerInterface $paymentStateManager,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $paymentStateManager->complete($payment)->shouldBeCalled();

        $this->completePayPalOrder($order);
    }

    function it_does_nothing_if_processing_payment_is_not_pay_pal(
        PaymentStateManagerInterface $paymentStateManager,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('stripe');

        $paymentStateManager->complete($payment)->shouldNotBeCalled();

        $this->completePayPalOrder($order);
    }

    function it_does_nothing_if_there_is_no_processing_payment_for_the_order(
        PaymentStateManagerInterface $paymentStateManager,
        OrderInterface $order
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn(null);

        $paymentStateManager->complete(Argument::any())->shouldNotBeCalled();

        $this->completePayPalOrder($order);
    }
}
