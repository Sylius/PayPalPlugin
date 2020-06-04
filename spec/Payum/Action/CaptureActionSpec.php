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
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Payum\Model\PayPalApi;

final class CaptureActionSpec extends ObjectBehavior
{
    function it_implements_action_interface(): void
    {
        $this->shouldImplement(ActionInterface::class);
    }

    function it_implements_api_aware_interface(): void
    {
        $this->shouldImplement(ApiAwareInterface::class);
    }

    function it_set_payment_details_during_request_execution(Capture $request, PaymentInterface $payment): void
    {
        $request->getModel()->willReturn($payment);
        $payment->setDetails(['status' => 200])->shouldBeCalled();

        $this->execute($request);
    }

    function it_throws_an_exception_if_request_type_is_invalid(GetStatus $request): void
    {
        $this
            ->shouldThrow(RequestNotSupportedException::class)
            ->during('execute', [$request])
        ;
    }

    function it_supports_capture_request_with_payment_as_first_model(
        Capture $request,
        PaymentInterface $payment
    ): void {
        $request->getModel()->willReturn($payment);

        $this->supports($request)->shouldReturn(true);
    }

    function it_does_not_support_request_other_than_capture(GetStatus $request): void
    {
        $this->supports($request)->shouldReturn(false);
    }

    function it_does_not_support_request_with_first_model_other_than_payment(Capture $request): void
    {
        $request->getModel()->willReturn('badObject');

        $this->supports($request)->shouldReturn(false);
    }

    function it_does_not_throw_an_exception_if_set_api_is_pay_pal_api(): void
    {
        $this
            ->shouldNotThrow(UnsupportedApiException::class)
            ->during('setApi', [new PayPalApi('TOKEN')])
        ;
    }

    function it_throws_an_exception_if_set_api_is_not_pay_pal_api(): void
    {
        $this
            ->shouldThrow(UnsupportedApiException::class)
            ->during('setApi', [new \stdClass()])
        ;
    }
}
