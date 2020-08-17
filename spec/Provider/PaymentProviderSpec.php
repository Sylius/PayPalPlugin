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

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;

final class PaymentProviderSpec extends ObjectBehavior
{
    function let(PaymentRepositoryInterface $paymentRepository): void
    {
        $this->beConstructedWith($paymentRepository);
    }

    function it_implements_payment_provider_interface(): void
    {
        $this->shouldImplement(PaymentProviderInterface::class);
    }

    function it_returns_payment_for_given_pay_pal_order_id(
        PaymentRepositoryInterface $paymentRepository,
        PaymentInterface $firstPayment,
        PaymentInterface $secondPayment,
        PaymentInterface $thirdPayment
    ): void {
        $paymentRepository->findAll()->willReturn([$firstPayment, $secondPayment, $thirdPayment]);

        $firstPayment->getDetails()->willReturn(['test' => 'TEST']);
        $secondPayment->getDetails()->willReturn(['paypal_order_id' => 'PP123']);
        $thirdPayment->getDetails()->willReturn(['paypal_order_id' => 'PP444']);

        $this->getByPayPalOrderId('PP444')->shouldReturn($thirdPayment);
    }

    function it_throws_exception_if_there_is_no_payment_with_given_paypal_order_id(
        PaymentRepositoryInterface $paymentRepository,
        PaymentInterface $firstPayment,
        PaymentInterface $secondPayment,
        PaymentInterface $thirdPayment
    ): void {
        $paymentRepository->findAll()->willReturn([$firstPayment, $secondPayment, $thirdPayment]);

        $firstPayment->getDetails()->willReturn(['test' => 'TEST']);
        $secondPayment->getDetails()->willReturn(['paypal_order_id' => 'PP123']);
        $thirdPayment->getDetails()->willReturn(['paypal_order_id' => 'PP444']);

        $this
            ->shouldThrow(PaymentNotFoundException::withPayPalOrderId('PP666'))
            ->during('getByPayPalOrderId', ['PP666'])
        ;
    }
}
