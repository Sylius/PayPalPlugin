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
use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final class CaptureActionSpec extends ObjectBehavior
{
    function let(
        AuthorizeClientApiInterface $authorizeClientApi,
        CreateOrderApiInterface $createOrderApi
    ): void {
        $this->beConstructedWith($authorizeClientApi, $createOrderApi);
    }

    function it_implements_action_interface(): void
    {
        $this->shouldImplement(ActionInterface::class);
    }

    function it_authorizes_seller_send_create_order_request_and_sets_order_response_data_on_payment(
        AuthorizeClientApiInterface $authorizeClientApi,
        CreateOrderApiInterface $createOrderApi,
        Capture $request,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $request->getModel()->willReturn($payment);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);

        $payment->getAmount()->willReturn(1000);
        $payment->getOrder()->willReturn($order);
        $order->getCurrencyCode()->willReturn('USD');

        $authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET')->willReturn('ACCESS_TOKEN');
        $createOrderApi->create('ACCESS_TOKEN', $payment)->willReturn(['status' => 'CAPTURED', 'id' => '123123']);

        $payment->setDetails([
            'status' => StatusAction::STATUS_CAPTURED,
            'paypal_order_id' => '123123',
        ])->shouldNotBeCalled();

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
}
