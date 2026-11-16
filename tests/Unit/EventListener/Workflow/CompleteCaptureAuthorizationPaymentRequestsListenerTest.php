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
use Sylius\PayPalPlugin\Completer\CaptureAuthorizationPaymentRequestCompleterInterface;
use Sylius\PayPalPlugin\EventListener\Workflow\CompleteCaptureAuthorizationPaymentRequestsListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

final class CompleteCaptureAuthorizationPaymentRequestsListenerTest extends TestCase
{
    public function test_it_completes_the_capture_requests_of_a_payment_completed_from_authorized(): void
    {
        $payment = new Payment();
        $completer = $this->createMock(CaptureAuthorizationPaymentRequestCompleterInterface::class);
        $completer->expects(self::once())->method('complete')->with($payment);

        (new CompleteCaptureAuthorizationPaymentRequestsListener($completer))(
            new CompletedEvent($payment, new Marking(['completed' => 1]), new Transition('complete', 'authorized', 'completed')),
        );
    }

    public function test_it_ignores_a_payment_completed_from_another_state(): void
    {
        $completer = $this->createMock(CaptureAuthorizationPaymentRequestCompleterInterface::class);
        $completer->expects(self::never())->method('complete');

        (new CompleteCaptureAuthorizationPaymentRequestsListener($completer))(
            new CompletedEvent(new Payment(), new Marking(['completed' => 1]), new Transition('complete', 'processing', 'completed')),
        );
    }
}
