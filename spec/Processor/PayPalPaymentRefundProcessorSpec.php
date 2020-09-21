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

use GuzzleHttp\Exception\ClientException;
use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final class PayPalPaymentRefundProcessorSpec extends ObjectBehavior
{
    function let(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        OrderDetailsApiInterface $orderDetailsApi,
        RefundPaymentApiInterface $refundOrderApi,
        PayPalAuthAssertionGeneratorInterface $payPalAuthAssertionGenerator,
        RefundReferenceNumberProviderInterface $refundReferenceNumberProvider
    ): void {
        $this->beConstructedWith(
            $authorizeClientApi,
            $orderDetailsApi,
            $refundOrderApi,
            $payPalAuthAssertionGenerator,
            $refundReferenceNumberProvider
        );
    }

    function it_implements_payment_refund_processor_interface(): void
    {
        $this->shouldImplement(PaymentRefundProcessorInterface::class);
    }

    function it_fully_refunds_payment_in_pay_pal(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        OrderDetailsApiInterface $orderDetailsApi,
        RefundPaymentApiInterface $refundOrderApi,
        PayPalAuthAssertionGeneratorInterface $payPalAuthAssertionGenerator,
        RefundReferenceNumberProviderInterface $refundReferenceNumberProvider,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        OrderInterface $order
    ): void {
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $payment->getDetails()->willReturn(['paypal_order_id' => '123123']);

        $authorizeClientApi->authorize($paymentMethod)->willReturn('TOKEN');
        $orderDetailsApi
            ->get('TOKEN', '123123')
            ->willReturn(['purchase_units' => [['payments' => ['captures' => [['id' => '555', 'status' => 'COMPLETED']]]]]])
        ;
        $payPalAuthAssertionGenerator->generate($paymentMethod)->willReturn('AUTH-ASSERTION');

        $payment->getAmount()->willReturn(1000);
        $payment->getOrder()->willReturn($order);
        $order->getCurrencyCode()->willReturn('USD');

        $refundReferenceNumberProvider->provide($payment)->willReturn('REFERENCE-NUMBER');

        $refundOrderApi
            ->refund('TOKEN', '555', 'AUTH-ASSERTION', 'REFERENCE-NUMBER', '10.00', 'USD')
            ->willReturn(['status' => 'COMPLETED', 'id' => '123123'])
        ;

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

    function it_does_nothing_if_payment_is_payment_has_not_pay_pal_order_id(
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

    function it_throws_exception_if_something_went_wrong_during_refunding_payment(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        OrderDetailsApiInterface $orderDetailsApi,
        RefundPaymentApiInterface $refundOrderApi,
        PayPalAuthAssertionGeneratorInterface $payPalAuthAssertionGenerator,
        RefundReferenceNumberProviderInterface $refundReferenceNumberProvider,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        OrderInterface $order
    ): void {
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $payment->getDetails()->willReturn(['paypal_order_id' => '123123']);

        $authorizeClientApi->authorize($paymentMethod)->willReturn('TOKEN');
        $orderDetailsApi
            ->get('TOKEN', '123123')
            ->willReturn(['purchase_units' => [['payments' => ['captures' => [['id' => '555', 'status' => 'COMPLETED']]]]]])
        ;
        $payPalAuthAssertionGenerator->generate($paymentMethod)->willReturn('AUTH-ASSERTION');

        $payment->getAmount()->willReturn(1000);
        $payment->getOrder()->willReturn($order);
        $order->getCurrencyCode()->willReturn('USD');

        $refundReferenceNumberProvider->provide($payment)->willReturn('REFERENCE-NUMBER');

        $refundOrderApi
            ->refund('TOKEN', '555', 'AUTH-ASSERTION', 'REFERENCE-NUMBER', '10.00', 'USD')
            ->willThrow(ClientException::class)
        ;

        $this
            ->shouldThrow(PayPalOrderRefundException::class)
            ->during('refund', [$payment])
        ;
    }
}
