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

use GuzzleHttp\ClientInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Request\Capture;
use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class CaptureActionSpec extends ObjectBehavior
{
    function let(ClientInterface $httpClient): void
    {
        $this->beConstructedWith($httpClient, 'https://paypal.facilitator.com');
    }

    function it_implements_action_interface(): void
    {
        $this->shouldImplement(ActionInterface::class);
    }

    function it_sends_create_order_request_and_sets_order_response_data_on_payment(
        ClientInterface $httpClient,
        Capture $request,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        OrderInterface $order,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $request->getModel()->willReturn($payment);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn([
            'client_id' => 'CLIENT_ID',
            'client_secret' => 'SEC$ET',
        ]);

        $payment->getOrder()->willReturn($order);
        $order->getCurrencyCode()->willReturn('GBP');
        $payment->getAmount()->willReturn(1000);

        $httpClient->request(
            'POST',
            'https://paypal.facilitator.com/create-order',
            [
                'verify' => false,
                'json' => [
                    'clientId' => 'CLIENT_ID',
                    'clientSecret' => 'SEC$ET',
                    'currencyCode' => 'GBP',
                    'amount' => '10.00',
                ]
            ]
        )->willReturn($response);

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"status": "CREATED", "order_id": "123123"}');

        $payment
            ->setDetails(['status' => 'CREATED', 'order_id' => '123123'])
            ->shouldBeCalled()
        ;

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
