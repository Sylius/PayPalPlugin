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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;

final class CompleteOrderApiSpec extends ObjectBehavior
{
    function let(Client $client): void
    {
        $this->beConstructedWith($client, 'https://api.test-paypal.com/', 'PARTNER_ATTRIBUTION_ID');
    }

    function it_implements_complete_order_api_interface(): void
    {
        $this->shouldImplement(CompleteOrderApiInterface::class);
    }

    function it_completes_pay_pal_order_with_given_id(
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
            'https://api.test-paypal.com/v2/checkout/orders/123123/capture',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Prefer' => 'return=representation',
                    'PayPal-Partner-Attribution-Id' => 'PARTNER_ATTRIBUTION_ID',
                    'Content-Type' => 'application/json',
                ],
            ]
        )->willReturn($response);
        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"status": "COMPLETED", "id": 123}');

        $this->complete('TOKEN', '123123')->shouldReturn(['status' => 'COMPLETED', 'id' => 123]);
    }
}
