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
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class RefundPaymentApiSpec extends ObjectBehavior
{
    function let(PayPalClientInterface $client): void
    {
        $this->beConstructedWith($client);
    }

    function it_implements_refund_order_api_interface(): void
    {
        $this->shouldImplement(RefundPaymentApiInterface::class);
    }

    function it_refunds_pay_pal_payment_with_given_id(PayPalClientInterface $client): void
    {
        $client
            ->post('v2/payments/captures/123123/refund', 'TOKEN')
            ->willReturn(['status' => 'COMPLETED', 'id' => '123123'])
        ;

        $this->refund('TOKEN', '123123')->shouldReturn(['status' => 'COMPLETED', 'id' => '123123']);
    }
}
