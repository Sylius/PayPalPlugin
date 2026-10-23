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

namespace Sylius\PayPalPlugin\Completer;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\CommandProvider\CaptureAuthorizationPaymentRequestCommandProvider;

final readonly class CaptureAuthorizationPaymentRequestCompleter implements CaptureAuthorizationPaymentRequestCompleterInterface
{
    /** @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository */
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function complete(PaymentInterface $payment): void
    {
        $paymentRequests = $this->paymentRequestRepository->findByPaymentIdAndStates(
            $payment->getId(),
            [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING],
        );

        foreach ($paymentRequests as $paymentRequest) {
            if (
                CaptureAuthorizationPaymentRequestCommandProvider::ACTION === $paymentRequest->getAction() &&
                $this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE)
            ) {
                $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
            }
        }
    }
}
