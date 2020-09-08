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

namespace spec\Sylius\PayPalPlugin\Entity;

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;

final class PayPalCredentialsSpec extends ObjectBehavior
{
    function let(PaymentMethodInterface $paymentMethod): void
    {
        $this->beConstructedWith('123ASD123', $paymentMethod, 'TOKEN', new \DateTime('2020-01-01 10:00:00'), 3600);
    }

    function it_implements_pay_pal_credentials_interface(): void
    {
        $this->shouldImplement(PayPalCredentialsInterface::class);
    }

    function it_has_a_payment_method(PaymentMethodInterface $paymentMethod): void
    {
        $this->paymentMethod()->shouldReturn($paymentMethod);
    }

    function it_has_a_access_token(): void
    {
        $this->accessToken()->shouldReturn('TOKEN');
    }

    function it_has_a_creation_time(): void
    {
        $this->creationTime()->shouldBeLike(new \DateTime('2020-01-01 10:00:00'));
    }

    function it_has_a_expiration_time(): void
    {
        $this->expirationTime()->shouldBeLike(new \DateTime('2020-01-01 11:00:00'));
    }

    function it_can_be_expired(): void
    {
        $this->isExpired()->shouldReturn(true);
    }
}
