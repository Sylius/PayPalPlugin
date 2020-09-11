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

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;

final class PaymentReferenceNumberProviderSpec extends ObjectBehavior
{
    function it_implements_payment_reference_number_provider_interface(): void
    {
        $this->shouldImplement(PaymentReferenceNumberProviderInterface::class);
    }

    function it_provides_reference_number_based_on_payment_id_and_creation_date(PaymentInterface $payment): void
    {
        $payment->getId()->willReturn(123);
        $payment->getCreatedAt()->willReturn(new \DateTime('10-03-2012'));

        $this->provide($payment)->shouldReturn('123-10-03-2012');
    }
}
