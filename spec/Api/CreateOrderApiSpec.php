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

namespace spec\Sylius\PayPalPlugin\Api;

use GuzzleHttp\Client;
use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;

final class CreateOrderApiSpec extends ObjectBehavior
{
    function let(Client $client): void
    {
        $this->beConstructedWith($client, 'https://api.test-paypal.com/');
    }

    function it_implements_create_order_api_interface(): void
    {
        $this->shouldImplement(CreateOrderApiInterface::class);
    }

    function it_creates_pay_pal_order_basing_on_given_payment(
        Client $client,
        PaymentInterface $payment,
        OrderInterface $order,
        ResponseInterface $response,
        StreamInterface $body,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(10000);
        $order->getCurrencyCode()->willReturn('PLN');

        $payment->getMethod()->willReturn($paymentMethod);
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $gatewayConfig->getConfig()->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id']
        );

        $client->request(
            'POST',
            'https://api.test-paypal.com/v2/checkout/orders',
            Argument::that(function (array $data): bool {
                return
                    $data['headers']['Authorization'] === 'Bearer TOKEN' &&
                    $data['json']['intent'] === 'CAPTURE' &&
                    $data['json']['purchase_units'][0]['amount']['value'] === 100 &&
                    $data['json']['purchase_units'][0]['amount']['currency_code'] === 'PLN'
                ;
            })
        )->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"status": "CREATED", "id": 123}');

        $this->create('TOKEN', $payment)->shouldReturn(['status' => 'CREATED', 'id' => 123]);
    }
}
