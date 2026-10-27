<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Unit\EventListener\Workflow;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\PayPalPlugin\EventListener\Workflow\RemovePayPalPaymentDetailsListener;
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemoverInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

final class RemovePayPalPaymentDetailsListenerTest extends TestCase
{
    public function test_it_removes_paypal_payment_details_from_the_order_whose_payment_was_selected(): void
    {
        $order = new Order();
        $remover = $this->createMock(PayPalPaymentDetailsRemoverInterface::class);
        $remover->expects(self::once())->method('removeFromOrder')->with($order);

        (new RemovePayPalPaymentDetailsListener($remover))($this->completedEvent($order));
    }

    public function test_it_does_nothing_for_a_subject_other_than_an_order(): void
    {
        $remover = $this->createMock(PayPalPaymentDetailsRemoverInterface::class);
        $remover->expects(self::never())->method('removeFromOrder');

        (new RemovePayPalPaymentDetailsListener($remover))($this->completedEvent(new Payment()));
    }

    private function completedEvent(object $subject): CompletedEvent
    {
        return new CompletedEvent($subject, new Marking(['payment_selected' => 1]), new Transition('select_payment', 'shipping_selected', 'payment_selected'));
    }
}
