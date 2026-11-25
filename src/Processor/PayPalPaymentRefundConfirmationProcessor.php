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
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\CommandHandler\RefundPayPalPaymentRequestHandler;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final readonly class PayPalPaymentRefundConfirmationProcessor implements PayPalPaymentRefundConfirmationProcessorInterface
{
    /** @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository */
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function confirm(PaymentInterface $payment, ?string $refundId): void
    {
        $details = PayPalPaymentDetails::fromPayment($payment);
        $payment->setDetails($details->withRefund($refundId ?? $details->refundId())->toArray());

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        }

        $paymentRequests = $this->paymentRequestRepository->findByPaymentIdAndStates(
            $payment->getId(),
            [PaymentRequestInterface::STATE_PROCESSING],
        );

        foreach ($paymentRequests as $paymentRequest) {
            if (
                PaymentRequestInterface::ACTION_REFUND === $paymentRequest->getAction() &&
                null !== $refundId &&
                $refundId === ($paymentRequest->getResponseData()[RefundPayPalPaymentRequestHandler::RESPONSE_REFUND_ID] ?? null)
            ) {
                $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
            }
        }
    }
}
