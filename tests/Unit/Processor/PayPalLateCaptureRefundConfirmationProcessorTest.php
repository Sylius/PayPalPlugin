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
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Processor\PayPalLateCaptureRefundConfirmationProcessor;

final class PayPalLateCaptureRefundConfirmationProcessorTest extends TestCase
{
    /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    private StateMachineInterface&MockObject $stateMachine;

    /** @var list<array{object, string}> */
    private array $appliedTransitions = [];

    private PayPalLateCaptureRefundConfirmationProcessor $processor;

    protected function setUp(): void
    {
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = [$subject, $transition];
        });

        $this->processor = new PayPalLateCaptureRefundConfirmationProcessor($this->paymentRequestRepository, $this->stateMachine);
    }

    public function test_it_stores_the_late_capture_refund_and_completes_the_processing_late_capture_refund_requests(): void
    {
        $payment = new Payment();
        $payment->setDetails([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'paypal_late_capture' => ['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR'],
        ]);
        $lateCaptureRefundRequest = $this->paymentRequest($payment, 'refund_late_capture');
        $refundRequest = $this->paymentRequest($payment, PaymentRequestInterface::ACTION_REFUND);

        $this->paymentRequestRepository
            ->method('findByPaymentIdAndStates')
            ->with($payment->getId(), [PaymentRequestInterface::STATE_PROCESSING])
            ->willReturn([$lateCaptureRefundRequest, $refundRequest])
        ;

        $this->processor->confirm($payment, 'REFUND_ID');

        self::assertSame([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'paypal_late_capture' => ['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR', 'refunded' => true, 'refund_id' => 'REFUND_ID'],
        ], $payment->getDetails());
        self::assertSame([[$lateCaptureRefundRequest, PaymentRequestTransitions::TRANSITION_COMPLETE]], $this->appliedTransitions);
    }

    public function test_it_keeps_the_stored_refund_id_when_the_confirmation_carries_none(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_late_capture' => ['id' => 'CAPTURE_ID', 'refunded' => true, 'refund_id' => 'REFUND_ID']]);
        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([]);

        $this->processor->confirm($payment, null);

        self::assertSame(['paypal_late_capture' => ['id' => 'CAPTURE_ID', 'refunded' => true, 'refund_id' => 'REFUND_ID']], $payment->getDetails());
    }

    public function test_it_does_nothing_with_a_payment_without_a_late_capture(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID']);
        $this->paymentRequestRepository->expects(self::never())->method('findByPaymentIdAndStates');

        $this->processor->confirm($payment, 'REFUND_ID');

        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID'], $payment->getDetails());
        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_leaves_processing_refund_requests_of_another_refund_or_of_no_refund_alone(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_late_capture' => ['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']]);
        $anotherRefundRequest = $this->paymentRequest($payment, 'refund_late_capture', 'ANOTHER_REFUND_ID');
        $unansweredRefundRequest = $this->paymentRequest($payment, 'refund_late_capture', null);
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
        $payment->setDetails(['paypal_late_capture' => ['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']]);
        $refundRequest = $this->paymentRequest($payment, 'refund_late_capture');
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
