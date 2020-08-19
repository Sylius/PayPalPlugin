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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;

final class PayPalPaymentRefundProcessorSpec extends ObjectBehavior
{
    function let(AuthorizeClientApiInterface $authorizeClientApi, RefundPaymentApiInterface $refundOrderApi): void
    {
        $this->beConstructedWith($authorizeClientApi, $refundOrderApi);
    }

    function it_implements_payment_refund_processor_interface(): void
    {
        $this->shouldImplement(PaymentRefundProcessorInterface::class);
    }

    function it_fully_refunds_payment_in_pay_pal(
        AuthorizeClientApiInterface $authorizeClientApi,
        RefundPaymentApiInterface $refundOrderApi,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);
        $payment->getDetails()->willReturn(['paypal_payment_id' => '123123']);

        $authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET')->willReturn('TOKEN');
        $refundOrderApi->refund('TOKEN', '123123')->willReturn(['status' => 'COMPLETED', 'id' => '123123']);

        $this->refund($payment);
    }

    function it_does_nothing_if_payment_is_not_pay_pal(
        RefundPaymentApiInterface $refundOrderApi,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('offline');
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);

        $refundOrderApi->refund(Argument::any())->shouldNotBeCalled();

        $this->refund($payment);
    }

    function it_does_nothing_if_payment_is_payment_has_not_pay_pal_payment_id(
        RefundPaymentApiInterface $refundOrderApi,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);
        $payment->getDetails()->willReturn([]);

        $refundOrderApi->refund(Argument::any())->shouldNotBeCalled();

        $this->refund($payment);
    }

    function it_throws_exception_if_refund_could_not_be_processed(
        AuthorizeClientApiInterface $authorizeClientApi,
        RefundPaymentApiInterface $refundOrderApi,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);
        $payment->getDetails()->willReturn(['paypal_payment_id' => '123123']);

        $authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET')->willReturn('TOKEN');
        $refundOrderApi->refund('TOKEN', '123123')->willReturn(['status' => 'FAILED']);

        $this
            ->shouldThrow(PayPalOrderRefundException::class)
            ->during('refund', [$payment])
        ;
    }
}
