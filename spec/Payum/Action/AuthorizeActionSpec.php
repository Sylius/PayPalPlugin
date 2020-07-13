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
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final class AuthorizeActionSpec extends ObjectBehavior
{
    function it_implements_action_interface(): void
    {
        $this->shouldImplement(ActionInterface::class);
    }

    function it_marks_payment_as_created(
        Authorize $request,
        PaymentInterface $payment
    ): void {
        $request->getModel()->willReturn($payment);
        $payment->setDetails(['status' => StatusAction::STATUS_CREATED])->shouldBeCalled();

        $this->execute($request);
    }

    function it_throws_an_exception_if_request_type_is_invalid(GetStatus $request): void
    {
        $this
            ->shouldThrow(RequestNotSupportedException::class)
            ->during('execute', [$request])
        ;
    }

    function it_supports_authorize_request_with_payment_as_first_model(
        Authorize $request,
        PaymentInterface $payment
    ): void {
        $request->getModel()->willReturn($payment);

        $this->supports($request)->shouldReturn(true);
    }

    function it_does_not_support_request_other_than_authorize(Capture $request): void
    {
        $this->supports($request)->shouldReturn(false);
    }

    function it_does_not_support_request_with_first_model_other_than_payment(Authorize $request): void
    {
        $request->getModel()->willReturn('badObject');

        $this->supports($request)->shouldReturn(false);
    }
}
