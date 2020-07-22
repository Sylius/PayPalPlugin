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

namespace spec\Sylius\PayPalPlugin\Provider;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;

final class OnboardedPayPalClientIdProviderSpec extends ObjectBehavior
{
    function let(PaymentMethodRepositoryInterface $paymentMethodRepository): void
    {
        $this->beConstructedWith($paymentMethodRepository);
    }

    function it_returns_client_id_from_payment_method_config(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $payPalPaymentMethod,
        PaymentMethodInterface $otherPaymentMethod,
        GatewayConfigInterface $payPalGatewayConfig,
        GatewayConfigInterface $otherGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository
            ->findEnabledForChannel($channel)
            ->willReturn([$otherPaymentMethod, $payPalPaymentMethod])
        ;

        $otherPaymentMethod->getGatewayConfig()->willReturn($otherGatewayConfig);
        $otherGatewayConfig->getFactoryName()->willReturn('other');

        $payPalPaymentMethod->getGatewayConfig()->willReturn($payPalGatewayConfig);
        $payPalGatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $payPalGatewayConfig->getConfig()->willReturn(['client_id' => '123123']);

        $this->getForChannel($channel)->shouldReturn('123123');
    }

    function it_throws_an_exception_if_there_is_no_pay_pal_payment_method_defined(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodInterface $otherPaymentMethod,
        GatewayConfigInterface $otherGatewayConfig,
        ChannelInterface $channel
    ): void {
        $paymentMethodRepository->findEnabledForChannel($channel)->willReturn([$otherPaymentMethod]);
        $otherPaymentMethod->getGatewayConfig()->willReturn($otherGatewayConfig);
        $otherGatewayConfig->getFactoryName()->willReturn('other');

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('getForChannel', [$channel])
        ;
    }
}
