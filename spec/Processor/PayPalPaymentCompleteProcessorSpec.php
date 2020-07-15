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

use Payum\Core\GatewayInterface;
use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Payum;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;

final class PayPalPaymentCompleteProcessorSpec extends ObjectBehavior
{
    function let(Payum $payum, PaymentStateManagerInterface $paymentStateManager): void
    {
        $this->beConstructedWith($payum, $paymentStateManager);
    }

    function it_completes_pay_pal_order(
        Payum $payum,
        PaymentStateManagerInterface $paymentStateManager,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        GatewayInterface $gateway
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getGatewayName()->willReturn('paypal');

        $payment->getDetails()->willReturn(['paypal_order_id' => '123123']);

        $payum->getGateway('paypal')->willReturn($gateway);
        $gateway->execute(Argument::that(function (CompleteOrder $request): bool {
            return $request->getOrderId() === '123123';
        }))->shouldBeCalled();

        $paymentStateManager->complete($payment)->shouldBeCalled();

        $this->completePayPalOrder($order);
    }

    function it_does_nothing_if_processing_payment_is_not_pay_pal(
        Payum $payum,
        PaymentStateManagerInterface $paymentStateManager,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn($payment);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getGatewayName()->willReturn('stripe');

        $payum->getGateway(Argument::any())->shouldNotBeCalled();

        $this->completePayPalOrder($order);
    }

    function it_does_nothing_if_there_is_no_processing_payment_for_the_order(
        Payum $payum,
        OrderInterface $order
    ): void {
        $order->getLastPayment(PaymentInterface::STATE_PROCESSING)->willReturn(null);

        $payum->getGateway(Argument::any())->shouldNotBeCalled();

        $this->completePayPalOrder($order);
    }
}
