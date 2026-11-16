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
use Sylius\Component\Core\Model\Payment;
use Sylius\PayPalPlugin\Checker\PaymentRefundCheckerInterface;
use Sylius\PayPalPlugin\EventListener\Workflow\PaymentRefundGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

final class PaymentRefundGuardListenerTest extends TestCase
{
    public function test_it_blocks_the_refund_the_checker_does_not_allow(): void
    {
        $event = $this->guardEvent($payment = new Payment());
        $checker = $this->createMock(PaymentRefundCheckerInterface::class);
        $checker->expects(self::once())->method('canBeRefunded')->with($payment)->willReturn(false);

        (new PaymentRefundGuardListener($checker))($event);

        self::assertTrue($event->isBlocked());
    }

    public function test_it_lets_the_refund_the_checker_allows_through(): void
    {
        $event = $this->guardEvent(new Payment());
        $checker = $this->createStub(PaymentRefundCheckerInterface::class);
        $checker->method('canBeRefunded')->willReturn(true);

        (new PaymentRefundGuardListener($checker))($event);

        self::assertFalse($event->isBlocked());
    }

    private function guardEvent(Payment $payment): GuardEvent
    {
        return new GuardEvent($payment, new Marking(['completed' => 1]), new Transition('refund', 'completed', 'refunded'));
    }
}
