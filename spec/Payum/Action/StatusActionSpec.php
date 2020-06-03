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

namespace spec\Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;

final class StatusActionSpec extends ObjectBehavior
{
    function it_implements_action_interface(): void
    {
        $this->shouldImplement(ActionInterface::class);
    }

    function it_always_marks_request_as_captured(GetStatus $request): void
    {
        $request->markCaptured()->shouldBeCalled();

        $this->execute($request);
    }

    function it_supports_get_status_request_with_payment_as_first_model(
        GetStatus $request,
        PaymentInterface $payment
    ): void {
        $request->getFirstModel()->willReturn($payment);

        $this->supports($request)->shouldReturn(true);
    }

    function it_does_not_support_request_other_than_get_status(Capture $request): void
    {
        $this->supports($request)->shouldReturn(false);
    }

    function it_does_not_support_request_with_first_model_other_than_payment(GetStatus $request): void
    {
        $request->getFirstModel()->willReturn('badObject');

        $this->supports($request)->shouldReturn(false);
    }
}
