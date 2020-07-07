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
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final class CaptureActionSpec extends ObjectBehavior
{
    function let(ClientInterface $client): void
    {
        $this->beConstructedWith($client);
    }

    function it_implements_action_interface(): void
    {
        $this->shouldImplement(ActionInterface::class);
    }

    function it_authorizes_seller_send_create_order_request_and_sets_order_response_data_on_payment(
        ClientInterface $client,
        Capture $request,
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig,
        ResponseInterface $authorizationResponse,
        ResponseInterface $createResponse,
        StreamInterface $authorizationBody,
        StreamInterface $createBody
    ): void {
        $request->getModel()->willReturn($payment);
        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);

        $payment->getAmount()->willReturn(1000);
        $payment->getOrder()->willReturn($order);
        $order->getCurrencyCode()->willReturn('USD');

        $client->request(
            'POST',
            'https://api.sandbox.paypal.com/v1/oauth2/token',
            Argument::that(function (array $data): bool {
                return
                    $data['auth'][0] === 'CLIENT_ID' &&
                    $data['auth'][1] === 'CLIENT_SECRET' &&
                    $data['form_params']['grant_type'] === 'client_credentials'
                ;
            })
        )->willReturn($authorizationResponse);

        $authorizationResponse->getBody()->willReturn($authorizationBody);
        $authorizationBody->getContents()->willReturn('{"access_token": "ACCESS_TOKEN"}');

        $client->request(
            'POST',
            'https://api.sandbox.paypal.com/v2/checkout/orders',
            Argument::that(function (array $data): bool {
                return
                    $data['headers']['Authorization'] === 'Bearer ACCESS_TOKEN' &&
                    $data['headers']['PayPal-Partner-Attribution-Id'] === 'sylius-ppcp4p-bn-code' &&
                    $data['json']['intent'] === 'CAPTURE' &&
                    $data['json']['purchase_units'][0]['amount']['currency_code'] === 'USD' &&
                    $data['json']['purchase_units'][0]['amount']['value'] === 10
                ;
            })
        )->willReturn($createResponse);

        $createResponse->getBody()->willReturn($createBody);
        $createBody->getContents()->willReturn('{"id": "123123", "status": "CREATED"}');

        $payment->setDetails([
            'status' => StatusAction::STATUS_CAPTURED,
            'paypal_order_id' => '123123',
        ])->shouldBeCalled();

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
