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
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class OrderDetailsApiSpec extends ObjectBehavior
{
    function let(PayPalClientInterface $client): void
    {
        $this->beConstructedWith($client);
    }

    function it_implements_pay_pal_order_details_provider_interface(): void
    {
        $this->shouldImplement(OrderDetailsApiInterface::class);
    }

    function it_provides_details_about_pay_pal_order(PayPalClientInterface $client): void
    {
        $client
            ->get('v2/checkout/orders/123123', 'TOKEN')
            ->willReturn(['total' => 1111])
        ;

        $this->get('TOKEN', '123123')->shouldReturn(['total' => 1111]);
    }
}
