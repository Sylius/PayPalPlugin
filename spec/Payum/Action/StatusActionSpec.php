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
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final class StatusActionSpec extends ObjectBehavior
{
    function it_implements_action_interface(): void
    {
        $this->shouldImplement(ActionInterface::class);
    }

    function it_marks_request_as_new(GetStatus $request, PaymentInterface $payment): void
    {
        $request->getFirstModel()->willReturn($payment);

        $request->getModel()->willReturn(['status' => StatusAction::STATUS_CREATED]);
        $request->markNew()->shouldBeCalled();

        $this->execute($request);
    }

    function it_marks_request_as_pending(GetStatus $request, PaymentInterface $payment): void
    {
        $request->getFirstModel()->willReturn($payment);

        $request->getModel()->willReturn(['status' => StatusAction::STATUS_CAPTURED]);
        $request->markPending()->shouldBeCalled();

        $this->execute($request);
    }

    function it_marks_request_as_captured(GetStatus $request, PaymentInterface $payment): void
    {
        $request->getFirstModel()->willReturn($payment);

        $request->getModel()->willReturn(['status' => 'COMPLETED']);
        $request->markCaptured()->shouldBeCalled();

        $this->execute($request);
    }

    function it_throws_an_exception_if_request_is_not_supported(Capture $request): void
    {
        $this
            ->shouldThrow(RequestNotSupportedException::class)
            ->during('execute', [$request])
        ;
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
