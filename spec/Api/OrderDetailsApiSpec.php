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
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;

final class OrderDetailsApiSpec extends ObjectBehavior
{
    function let(Client $client): void
    {
        $this->beConstructedWith($client, 'https://api.test-paypal.com/', 'PARTNER_ATTRIBUTION_ID');
    }

    function it_implements_pay_pal_order_details_provider_interface(): void
    {
        $this->shouldImplement(OrderDetailsApiInterface::class);
    }

    function it_provides_details_about_pay_pal_order(
        Client $client,
        AuthorizeClientApiInterface $authorizeClientApi,
        ResponseInterface $detailsResponse,
        StreamInterface $detailsBody
    ): void {
        $client->request(
            'GET',
            'https://api.test-paypal.com/v2/checkout/orders/123123',
            [
                'headers' => [
                    'Authorization' => 'Bearer TOKEN',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'PARTNER_ATTRIBUTION_ID',
                ],
            ]
        )->willReturn($detailsResponse);
        $detailsResponse->getBody()->willReturn($detailsBody);
        $detailsBody->getContents()->willReturn('{"total": 1111}');

        $this->get('TOKEN', '123123')->shouldReturn(['total' => 1111]);
    }
}
