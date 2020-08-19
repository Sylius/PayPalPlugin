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
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\RefundOrderApiInterface;

final class RefundOrderApiSpec extends ObjectBehavior
{
    function let(Client $client): void
    {
        $this->beConstructedWith($client, 'https://api.test-paypal.com/');
    }

    function it_implements_refund_order_api_interface(): void
    {
        $this->shouldImplement(RefundOrderApiInterface::class);
    }

    function it_refunds_pay_pal_order_with_given_id(
        Client $client,
        PaymentInterface $payment,
        OrderInterface $order,
        ResponseInterface $response,
        StreamInterface $body
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(10000);
        $order->getCurrencyCode()->willReturn('PLN');

        $client->request(
            'POST',
            'https://api.test-paypal.com/v2/payments/captures/123123/refund',
            Argument::that(function(array $options): bool {
                return
                    $options['headers']['Authorization'] === 'Bearer TOKEN' &&
                    $options['headers']['PayPal-Partner-Attribution-Id'] === 'sylius-ppcp4p-bn-code' &&
                    $options['headers']['Content-Type'] === 'application/json' &&
                    is_string($options['headers']['PayPal-Request-Id'])
                ;
            })
        )->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"status": "COMPLETED", "id": "123123"}');

        $this->refund('TOKEN', '123123')->shouldReturn(['status' => 'COMPLETED', 'id' => '123123']);
    }
}
