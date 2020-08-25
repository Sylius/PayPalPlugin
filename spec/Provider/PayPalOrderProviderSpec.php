<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class PayPalOrderProviderSpec extends ObjectBehavior
{
    function let(PaymentProviderInterface $paymentProvider): void
    {
        $this->beConstructedWith($paymentProvider);
    }

    function it_provides_data_by_request_if_order_is_found(
        Request $request,
        PaymentProviderInterface $paymentProvider,
        OrderInterface $order,
        PaymentInterface $payment
    ): void {
        $request->getContent(false)->willReturn('{"resource":{"id":"PAYP_ORDER_ID"}}');

        $paymentProvider->getByPayPalOrderId('PAYP_ORDER_ID')->willReturn($payment);

        $payment->getOrder()->willReturn($order);

        $this->provide($request)->shouldReturn($order);
    }

    function it_throws_error_if_request_is_not_valid(
        Request $request
    ): void {
        $request->getContent(false)->willReturn('{}');

        $this->shouldThrow(\InvalidArgumentException::class)->during('provide', [$request]);
    }

    function it_throws_error_if_payment_is_not_found(
        Request $request,
        PaymentProviderInterface $paymentProvider
    ): void {
        $request->getContent(false)->willReturn('{"resource":{"id":"WRONG_ID"}}');
        $paymentProvider->getByPayPalOrderId('WRONG_ID')->willThrow(PaymentNotFoundException::class);

        $this->shouldThrow(PaymentNotFoundException::class)->during('provide', [$request]);
    }
}
