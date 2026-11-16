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
use Sylius\PayPalPlugin\Checker\PaymentCompletionCheckerInterface;
use Sylius\PayPalPlugin\EventListener\Workflow\PaymentCompleteGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

final class PaymentCompleteGuardListenerTest extends TestCase
{
    public function test_it_blocks_the_completion_the_checker_does_not_allow(): void
    {
        $event = $this->guardEvent($payment = new Payment());
        $checker = $this->createMock(PaymentCompletionCheckerInterface::class);
        $checker->expects(self::once())->method('canBeCompleted')->with($payment)->willReturn(false);

        (new PaymentCompleteGuardListener($checker))($event);

        self::assertTrue($event->isBlocked());
    }

    public function test_it_lets_the_completion_the_checker_allows_through(): void
    {
        $event = $this->guardEvent(new Payment());
        $checker = $this->createStub(PaymentCompletionCheckerInterface::class);
        $checker->method('canBeCompleted')->willReturn(true);

        (new PaymentCompleteGuardListener($checker))($event);

        self::assertFalse($event->isBlocked());
    }

    private function guardEvent(Payment $payment): GuardEvent
    {
        return new GuardEvent($payment, new Marking(['authorized' => 1]), new Transition('complete', 'authorized', 'completed'));
    }
}
