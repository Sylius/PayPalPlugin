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

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Exception\UpdateHandlingException;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;

final class UiPayPalPaymentRefundProcessorSpec extends ObjectBehavior
{
    function let(PaymentRefundProcessorInterface $paymentRefundProcessor): void
    {
        $this->beConstructedWith($paymentRefundProcessor);
    }

    function it_implements_payment_refund_processor_interface(): void
    {
        $this->shouldImplement(PaymentRefundProcessorInterface::class);
    }

    function it_throws_exception_if_refund_has_fails(
        PaymentRefundProcessorInterface $paymentRefundProcessor,
        PaymentInterface $payment
    ): void {
        $paymentRefundProcessor->refund($payment)->willThrow(PayPalOrderRefundException::class);

        $this->shouldThrow(UpdateHandlingException::class)->during('refund', [$payment]);
    }

    function it_does_nothing_if_refund_was_successful(
        PaymentRefundProcessorInterface $paymentRefundProcessor,
        PaymentInterface $payment
    ): void {
        $paymentRefundProcessor->refund($payment)->shouldBeCalled();

        $this->refund($payment);
    }
}
