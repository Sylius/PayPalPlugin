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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class CompleteOrderApiSpec extends ObjectBehavior
{
    function let(PayPalClientInterface $client): void
    {
        $this->beConstructedWith($client);
    }

    function it_implements_complete_order_api_interface(): void
    {
        $this->shouldImplement(CompleteOrderApiInterface::class);
    }

    function it_completes_pay_pal_order_with_given_id(
        PayPalClientInterface $client,
        PaymentInterface $payment,
        OrderInterface $order
    ): void {
        $payment->getOrder()->willReturn($order);
        $payment->getAmount()->willReturn(10000);
        $order->getCurrencyCode()->willReturn('PLN');

        $client
            ->post('v2/checkout/orders/123123/capture', 'TOKEN')
            ->willReturn(['status' => 'COMPLETED', 'id' => 123])
        ;

        $this->complete('TOKEN', '123123')->shouldReturn(['status' => 'COMPLETED', 'id' => 123]);
    }
}
