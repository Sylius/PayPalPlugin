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
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalOrderUpdateException;

final class UpdateOrderApiSpec extends ObjectBehavior
{
    function let(Client $client): void
    {
        $this->beConstructedWith($client, 'https://api.test-paypal.com/', 'PARTNER-ATTRIBUTION-ID');
    }

    function it_implements_update_order_api_interface(): void
    {
        $this->shouldImplement(UpdateOrderApiInterface::class);
    }

    function it_updates_pay_pal_order_with_given_new_total(
        Client $client,
        ResponseInterface $response
    ): void {
        $client->request(
            'PATCH',
            'https://api.test-paypal.com/v2/checkout/orders/ORDER-ID',
            Argument::that(function (array $data): bool {
                return
                    $data['headers']['Authorization'] === 'Bearer TOKEN' &&
                    $data['headers']['PayPal-Partner-Attribution-Id'] === 'PARTNER-ATTRIBUTION-ID' &&
                    $data['json'][0]['op'] === 'replace' &&
                    $data['json'][0]['path'] === '/purchase_units/@reference_id==\'default\'/amount' &&
                    $data['json'][0]['value']['value'] === '11.22' &&
                    $data['json'][0]['value']['currency_code'] === 'USD'
                ;
            })
        )->willReturn($response);
        $response->getStatusCode()->willReturn(204);

        $this->update('TOKEN', 'ORDER-ID', '11.22', 'USD');
    }

    function it_throws_an_exception_if_update_is_not_successful(
        Client $client,
        ResponseInterface $response
    ): void {
        $client->request(
            'PATCH',
            'https://api.test-paypal.com/v2/checkout/orders/ORDER-ID',
            Argument::that(function (array $data): bool {
                return
                    $data['headers']['Authorization'] === 'Bearer TOKEN' &&
                    $data['headers']['PayPal-Partner-Attribution-Id'] === 'PARTNER-ATTRIBUTION-ID' &&
                    $data['json'][0]['op'] === 'replace' &&
                    $data['json'][0]['path'] === '/purchase_units/@reference_id==\'default\'/amount' &&
                    $data['json'][0]['value']['value'] === '11.22' &&
                    $data['json'][0]['value']['currency_code'] === 'USD'
                ;
            })
        )->willReturn($response);
        $response->getStatusCode()->willReturn(500);

        $this
            ->shouldThrow(PayPalOrderUpdateException::class)
            ->during('update', ['TOKEN', 'ORDER-ID', '11.22', 'USD'])
        ;
    }
}
