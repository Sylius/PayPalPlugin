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

namespace Tests\Sylius\PayPalPlugin\Unit\Completer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Completer\CaptureAuthorizationPaymentRequestCompleter;

final class CaptureAuthorizationPaymentRequestCompleterTest extends TestCase
{
    public function test_it_completes_the_open_capture_authorization_requests_of_the_payment(): void
    {
        $payment = new Payment();
        $capture = $this->paymentRequest('capture_authorization');
        $refund = $this->paymentRequest(PaymentRequestInterface::ACTION_REFUND);

        /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject $paymentRequestRepository */
        $paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $paymentRequestRepository
            ->method('findByPaymentIdAndStates')
            ->with($payment->getId(), [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING])
            ->willReturn([$capture, $refund])
        ;
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(true);
        $stateMachine->expects(self::once())->method('apply')->with($capture, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);

        (new CaptureAuthorizationPaymentRequestCompleter($paymentRequestRepository, $stateMachine))->complete($payment);
    }

    public function test_it_leaves_a_request_that_cannot_be_completed_alone(): void
    {
        /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject $paymentRequestRepository */
        $paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([$this->paymentRequest('capture_authorization')]);
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(false);
        $stateMachine->expects(self::never())->method('apply');

        (new CaptureAuthorizationPaymentRequestCompleter($paymentRequestRepository, $stateMachine))->complete(new Payment());
    }

    private function paymentRequest(string $action): PaymentRequestInterface&MockObject
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn($action);

        return $paymentRequest;
    }
}
