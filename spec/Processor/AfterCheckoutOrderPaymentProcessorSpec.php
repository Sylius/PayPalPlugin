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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class AfterCheckoutOrderPaymentProcessorSpec extends ObjectBehavior
{
    function let(OrderProcessorInterface $baseOrderPaymentProcessor): void
    {
        $this->beConstructedWith($baseOrderPaymentProcessor);
    }

    function it_implements_order_processor_interface(): void
    {
        $this->shouldImplement(OrderProcessorInterface::class);
    }

    function it_does_nothing_if_order_is_not_completed(
        OrderProcessorInterface $baseOrderPaymentProcessor,
        OrderInterface $order
    ): void {
        $order->getCheckoutState()->willReturn(OrderCheckoutStates::STATE_ADDRESSED);

        $baseOrderPaymentProcessor->process($order)->shouldNotBeCalled();

        $this->process($order);
    }

    function it_uses_processor_if_order_is_completed(
        OrderProcessorInterface $baseOrderPaymentProcessor,
        OrderInterface $order
    ): void {
        $order->getCheckoutState()->willReturn(OrderCheckoutStates::STATE_COMPLETED);

        $baseOrderPaymentProcessor->process($order)->shouldBeCalled();

        $this->process($order);
    }
}
