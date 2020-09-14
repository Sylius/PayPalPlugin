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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;
use Sylius\PayPalPlugin\Resolver\CompleteOrderPaymentResolverInterface;

final class PayPalPaymentCompleteProcessorSpec extends ObjectBehavior
{
    function let(Payum $payum): void
    {
        $this->beConstructedWith($payum);
    }

    function it_implements_payment_complete_processor_interface(): void
    {
        $this->shouldImplement(PaymentCompleteProcessorInterface::class);
    }

    function it_completes_payment_in_pay_pal(
        Payum $payum,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        GatewayInterface $gateway,
        CompleteOrderPaymentResolverInterface $completeOrderPaymentResolver
    ): void {
        $payment->getDetails()->willReturn(['paypal_order_id' => '123123']);

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getGatewayName()->willReturn('paypal');

        $payum->getGateway('paypal')->willReturn($gateway);
        $gateway->execute(Argument::that(function (CompleteOrder $request): bool {
            return $request->getOrderId() === '123123';
        }))->shouldBeCalled();

        $this->completePayment($payment);
    }

    function it_does_nothing_if_payment_has_no_pay_pal_order_id_set(
        Payum $payum,
        PaymentInterface $payment,
        GatewayInterface $gateway
    ): void {
        $payment->getDetails()->willReturn([]);

        $payum->getGateway('paypal')->shouldNotBeCalled();

        $this->completePayment($payment);
    }
}
