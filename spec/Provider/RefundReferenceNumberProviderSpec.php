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
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final class RefundReferenceNumberProviderSpec extends ObjectBehavior
{
    function it_implements_refund_reference_number_provider_interface(): void
    {
        $this->shouldImplement(RefundReferenceNumberProviderInterface::class);
    }

    function it_provides_reference_number_based_on_payment_id_and_current_date(PaymentInterface $payment): void
    {
        $payment->getId()->willReturn(123);

        $this->provide($payment)->shouldReturn('123-' . (new \DateTime())->format('d-m-Y'));
    }
}
