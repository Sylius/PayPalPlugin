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

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class UpdateOrderApiSpec extends ObjectBehavior
{
    function let(PayPalClientInterface $client): void
    {
        $this->beConstructedWith($client);
    }

    function it_implements_update_order_api_interface(): void
    {
        $this->shouldImplement(UpdateOrderApiInterface::class);
    }

    function it_updates_pay_pal_order_with_given_new_total(PayPalClientInterface $client): void
    {
        $client->patch(
            'v2/checkout/orders/ORDER-ID',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data[0]['op'] === 'replace' &&
                    $data[0]['path'] === '/purchase_units/@reference_id==\'default\'/amount' &&
                    $data[0]['value']['value'] === '11.22' &&
                    $data[0]['value']['currency_code'] === 'USD'
                ;
            })
        )->shouldBeCalled();

        $this->update('TOKEN', 'ORDER-ID', '11.22', 'USD');
    }
}
