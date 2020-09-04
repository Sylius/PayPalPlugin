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

namespace spec\Sylius\PayPalPlugin\Updater;

use Doctrine\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final class PayPalPaymentUpdaterSpec extends ObjectBehavior
{
    function let(ObjectManager $paymentManager): void
    {
        $this->beConstructedWith($paymentManager);
    }

    function it_implements_payment_updater_interface(): void
    {
        $this->shouldImplement(PaymentUpdaterInterface::class);
    }

    function it_updates_payment_amount(
        ObjectManager $paymentManager,
        PaymentInterface $payment
    ): void {
        $payment->setAmount(1000)->shouldBeCalled();
        $paymentManager->flush();

        $this->updateAmount($payment, 1000);
    }
}
