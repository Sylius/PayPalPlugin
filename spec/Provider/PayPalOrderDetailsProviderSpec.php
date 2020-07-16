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

namespace spec\Sylius\PayPalPlugin\Provider;

use GuzzleHttp\Client;
use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Provider\PayPalOrderDetailsProviderInterface;

final class PayPalOrderDetailsProviderSpec extends ObjectBehavior
{
    function let(Client $client, AuthorizeClientApiInterface $authorizeClientApi): void
    {
        $this->beConstructedWith($client, $authorizeClientApi);
    }

    function it_implements_pay_pal_order_details_provider_interface(): void
    {
        $this->shouldImplement(PayPalOrderDetailsProviderInterface::class);
    }

    function it_provides_details_about_pay_pal_order(
        Client $client,
        AuthorizeClientApiInterface $authorizeClientApi,
        ResponseInterface $detailsResponse,
        StreamInterface $detailsBody
    ): void {
        $authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET')->willReturn('111222');

        $client->request(
            'GET',
            'https://api.sandbox.paypal.com/v2/checkout/orders/123123',
            [
                'headers' => [
                    'Authorization' => 'Bearer 111222',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'sylius-ppcp4p-bn-code',
                ],
            ]
        )->willReturn($detailsResponse);
        $detailsResponse->getBody()->willReturn($detailsBody);
        $detailsBody->getContents()->willReturn('{"total": 1111}');

        $this->provide('CLIENT_ID', 'CLIENT_SECRET', '123123')->shouldReturn(['total' => 1111]);
    }
}
