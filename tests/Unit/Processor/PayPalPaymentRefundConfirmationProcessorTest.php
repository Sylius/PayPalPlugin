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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\PaymentRequest;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundConfirmationProcessor;

final class PayPalPaymentRefundConfirmationProcessorTest extends TestCase
{
    /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    private StateMachineInterface&MockObject $stateMachine;

    /** @var list<array{object, string}> */
    private array $appliedTransitions = [];

    private PayPalPaymentRefundConfirmationProcessor $processor;

    protected function setUp(): void
    {
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = [$subject, $transition];
        });

        $this->processor = new PayPalPaymentRefundConfirmationProcessor($this->paymentRequestRepository, $this->stateMachine);
    }

    public function test_it_stores_the_refund_before_refunding_the_payment_and_completes_the_processing_refund_requests(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID']);
        $refundRequest = $this->paymentRequest($payment, PaymentRequestInterface::ACTION_REFUND);
        $captureRequest = $this->paymentRequest($payment, PaymentRequestInterface::ACTION_CAPTURE);

        $this->stateMachine
            ->method('can')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)
            ->willReturnCallback(static fn (): bool => true === ($payment->getDetails()['refunded'] ?? false))
        ;
        $this->paymentRequestRepository
            ->method('findByPaymentIdAndStates')
            ->with($payment->getId(), [PaymentRequestInterface::STATE_PROCESSING])
            ->willReturn([$refundRequest, $captureRequest])
        ;

        $this->processor->confirm($payment, 'REFUND_ID');

        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'refunded' => true, 'refund_id' => 'REFUND_ID'], $payment->getDetails());
        self::assertSame([
            [$payment, PaymentTransitions::TRANSITION_REFUND],
            [$refundRequest, PaymentRequestTransitions::TRANSITION_COMPLETE],
        ], $this->appliedTransitions);
    }

    public function test_it_only_stores_the_refund_of_a_payment_that_cannot_be_refunded(): void
    {
        $payment = new Payment();
        $this->stateMachine->method('can')->willReturn(false);
        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([]);

        $this->processor->confirm($payment, null);

        self::assertSame(['refunded' => true, 'refund_id' => null], $payment->getDetails());
        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_leaves_processing_refund_requests_of_another_refund_or_of_no_refund_alone(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID']);
        $anotherRefundRequest = $this->paymentRequest($payment, PaymentRequestInterface::ACTION_REFUND, 'ANOTHER_REFUND_ID');
        $unansweredRefundRequest = $this->paymentRequest($payment, PaymentRequestInterface::ACTION_REFUND, null);
        $this->stateMachine->method('can')->willReturn(true);
        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([$anotherRefundRequest, $unansweredRefundRequest]);

        $this->processor->confirm($payment, 'REFUND_ID');

        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $anotherRefundRequest->getState());
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $unansweredRefundRequest->getState());
        self::assertNotContains([$anotherRefundRequest, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
        self::assertNotContains([$unansweredRefundRequest, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_completes_no_refund_request_when_the_confirmation_names_no_refund(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID']);
        $refundRequest = $this->paymentRequest($payment, PaymentRequestInterface::ACTION_REFUND);
        $this->stateMachine->method('can')->willReturn(true);
        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([$refundRequest]);

        $this->processor->confirm($payment, null);

        self::assertNotContains([$refundRequest, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    private function paymentRequest(Payment $payment, string $action, ?string $refundId = 'REFUND_ID'): PaymentRequestInterface
    {
        $paymentRequest = new PaymentRequest($payment, new PaymentMethod());
        $paymentRequest->setAction($action);
        $paymentRequest->setState(PaymentRequestInterface::STATE_PROCESSING);
        $paymentRequest->setResponseData(null === $refundId ? [] : ['refund_id' => $refundId, 'refund_status' => 'PENDING']);

        return $paymentRequest;
    }
}
