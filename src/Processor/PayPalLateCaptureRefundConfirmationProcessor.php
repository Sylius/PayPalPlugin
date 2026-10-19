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

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\CommandProvider\RefundLateCapturePaymentRequestCommandProvider;

final readonly class PayPalLateCaptureRefundConfirmationProcessor implements PayPalLateCaptureRefundConfirmationProcessorInterface
{
    /** @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository */
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function confirm(PaymentInterface $payment, ?string $refundId): void
    {
        $details = $payment->getDetails();
        $lateCapture = $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] ?? null;
        if (!is_array($lateCapture)) {
            return;
        }

        $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] = array_merge($lateCapture, [
            'refunded' => true,
            'refund_id' => $refundId ?? (is_string($lateCapture['refund_id'] ?? null) ? $lateCapture['refund_id'] : null),
        ]);
        $payment->setDetails($details);

        $paymentRequests = $this->paymentRequestRepository->findByPaymentIdAndStates(
            $payment->getId(),
            [PaymentRequestInterface::STATE_PROCESSING],
        );

        foreach ($paymentRequests as $paymentRequest) {
            if (RefundLateCapturePaymentRequestCommandProvider::ACTION === $paymentRequest->getAction()) {
                $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
            }
        }
    }
}
