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

namespace spec\Sylius\PayPalPlugin\Payum\Request;

use Payum\Core\Request\Generic;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentInterface;

final class CompleteOrderSpec extends ObjectBehavior
{
    function let(PaymentInterface $payment): void
    {
        $this->beConstructedWith($payment, '123123');
    }

    function it_is_generic_action(): void
    {
        $this->shouldHaveType(Generic::class);
    }

    function it_has_an_order_id(): void
    {
        $this->getOrderId()->shouldReturn('123123');
    }
}
