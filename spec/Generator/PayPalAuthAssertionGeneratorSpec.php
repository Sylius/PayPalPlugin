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

namespace spec\Sylius\PayPalPlugin\Generator;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;

final class PayPalAuthAssertionGeneratorSpec extends ObjectBehavior
{
    function it_implements_pay_pal_auth_assertion_generator_interface(): void
    {
        $this->shouldImplement(PayPalAuthAssertionGeneratorInterface::class);
    }

    function it_generates_auth_assertion_based_on_payment_method_config(
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'merchant_id' => 'MERCHANT_ID']);

        $this
            ->generate($paymentMethod)
            ->shouldReturn('eyJhbGciOiJub25lIn0=.eyJpc3MiOiJDTElFTlRfSUQiLCJwYXllcl9pZCI6Ik1FUkNIQU5UX0lEIn0=.')
        ;
    }

    function it_throws_an_exception_if_gateway_config_does_not_have_proper_values_set(
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig, $gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['merchant_id' => 'MERCHANT_ID'], ['client_id' => 'CLIENT_ID']);

        $this->shouldThrow(\InvalidArgumentException::class)->during('generate', [$paymentMethod]);
        $this->shouldThrow(\InvalidArgumentException::class)->during('generate', [$paymentMethod]);
    }
}
