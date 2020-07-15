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

namespace spec\Sylius\PayPalPlugin\Resolver;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Exception\UnresolvedDefaultPaymentMethodException;
use Sylius\Component\Payment\Resolver\DefaultPaymentMethodResolverInterface;

final class PayPalDefaultPaymentMethodResolverSpec extends ObjectBehavior
{
    function let(DefaultPaymentMethodResolverInterface $decoratedDefaultPaymentMethodResolver, PaymentMethodRepositoryInterface $paymentMethodRepository): void
    {
        $this->beConstructedWith($decoratedDefaultPaymentMethodResolver, $paymentMethodRepository);
    }

    function it_implements_default_payment_method_resolver_interface(): void
    {
        $this->shouldImplement(DefaultPaymentMethodResolverInterface::class);
    }

    function it_returns_prioritised_payment_method_for_channel(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ChannelInterface $channel,
        PaymentMethodInterface $firstPayment,
        PaymentMethodInterface $secondPayment,
        GatewayConfigInterface $firstGatewayConfig,
        GatewayConfigInterface $secondGatewayConfig,
        PaymentInterface $subject,
        OrderInterface $order
    ): void {
        $firstPayment->getGatewayConfig()->willReturn($firstGatewayConfig);
        $firstGatewayConfig->getFactoryName()->willReturn('new.payment');

        $secondPayment->getGatewayConfig()->willReturn($secondGatewayConfig);
        $secondGatewayConfig->getFactoryName()->willReturn('prioritised.payment');

        $paymentMethodRepository->findEnabledForChannel($channel)->willReturn([$firstPayment, $secondPayment]);

        $subject->getOrder()->willReturn($order);
        $order->getChannel()->willReturn($channel);

        $this->getDefaultPaymentMethod($subject, 'prioritised.payment')->shouldReturn($secondPayment);
    }

    function it_returns_first_available_payment_method_if_priotitised_payment_method_is_invalid(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ChannelInterface $channel,
        PaymentMethodInterface $firstPayment,
        PaymentMethodInterface $secondPayment,
        GatewayConfigInterface $firstGatewayConfig,
        GatewayConfigInterface $secondGatewayConfig,
        PaymentInterface $subject,
        OrderInterface $order
    ): void {
        $firstPayment->getGatewayConfig()->willReturn($firstGatewayConfig);
        $firstGatewayConfig->getFactoryName()->willReturn('payment1');

        $secondPayment->getGatewayConfig()->willReturn($secondGatewayConfig);
        $secondGatewayConfig->getFactoryName()->willReturn('payment2');

        $paymentMethodRepository->findEnabledForChannel($channel)->willReturn([$firstPayment, $secondPayment]);

        $subject->getOrder()->willReturn($order);
        $order->getChannel()->willReturn($channel);

        $this->getDefaultPaymentMethod($subject, 'prioritised')->shouldReturn($firstPayment);
    }

    function it_throws_error_if_there_is_no_available_payment(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ChannelInterface $channel,
        PaymentInterface $subject,
        OrderInterface $order
    ): void {
        $paymentMethodRepository->findEnabledForChannel($channel)->willReturn([]);

        $subject->getOrder()->willReturn($order);
        $order->getChannel()->willReturn($channel);

        $this->shouldThrow(UnresolvedDefaultPaymentMethodException::class)->during('getDefaultPaymentMethod', [$subject, 'prioritised']);
    }
}
