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

namespace Sylius\PayPalPlugin\CommandHandler;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Command\CapturePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifierInterface;

final class CapturePayPalOrderPaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const THREE_D_SECURE = 'three_d_secure';

    public const THREE_D_SECURE_RETRY = 'retry';

    public const THREE_D_SECURE_DECLINED = 'declined';

    public const RESPONSE_AWAITING_PAYER = 'awaiting_payer';

    private const PAYER_ACTION_REQUIRED_ORDER_STATUS = 'PAYER_ACTION_REQUIRED';

    private const UNAPPROVED_ORDER_STATUSES = ['CREATED', 'SAVED', self::PAYER_ACTION_REQUIRED_ORDER_STATUS, 'VOIDED'];

    private const REFUSED_CAPTURE_STATUSES = [PayPalCapture::STATUS_DECLINED, PayPalCapture::STATUS_FAILED];

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly PaymentThreeDSecureVerifierInterface $paymentThreeDSecureVerifier,
        private readonly PaymentCaptureProcessorInterface $paymentCaptureProcessor,
        private readonly PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(CapturePayPalOrderPaymentRequest $capturePayPalOrderPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($capturePayPalOrderPaymentRequest);

        if (PaymentRequestInterface::STATE_PROCESSING !== $paymentRequest->getState()) {
            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();

        if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
            $paymentRequest->setResponseData([self::RESPONSE_REASON => 'Another PayPal order has already paid the payment.']);
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);

            return;
        }

        if (!PayPalPaymentDetails::fromPayment($payment)->hasPayPalOrderId()) {
            $this->failWithReason($paymentRequest, 'The payment carries no PayPal order id.');

            return;
        }

        try {
            $this->paymentThreeDSecureVerifier->verify($payment);
        } catch (ThreeDSecureAuthenticationFailedException $exception) {
            $this->endWithFailedAuthentication($paymentRequest, $payment, $exception->isRetryable());

            return;
        }

        $payPalOrder = $this->paymentCaptureProcessor->capture($payment);
        $capture = PayPalCapture::fromPayPalOrder($payPalOrder);

        if (null !== $capture) {
            $this->paymentSettlementProcessor->settle($payment, $payPalOrder);
        }

        $refusalReason = $this->refusalReason($payPalOrder, $capture);
        if (null !== $refusalReason) {
            $this->failWithReason($paymentRequest, $refusalReason);

            return;
        }

        if (null === $capture) {
            $this->isPayerAtTheBank($payment, $payPalOrder)
                ? $this->awaitPayer($paymentRequest)
                : $this->cancelUnapproved($paymentRequest);

            return;
        }

        $this->complete($paymentRequest, $payment);
    }

    private function endWithFailedAuthentication(PaymentRequestInterface $paymentRequest, PaymentInterface $payment, bool $retryable): void
    {
        if ($retryable) {
            $paymentRequest->setResponseData([
                self::RESPONSE_REASON => 'The 3D Secure authentication of the card did not finish.',
                self::THREE_D_SECURE => self::THREE_D_SECURE_RETRY,
            ]);
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);

            return;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        $paymentRequest->setResponseData([
            self::RESPONSE_REASON => 'The 3D Secure authentication refused the card.',
            self::THREE_D_SECURE => self::THREE_D_SECURE_DECLINED,
        ]);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);
    }

    /** @param array<string, mixed> $payPalOrder */
    private function refusalReason(array $payPalOrder, ?PayPalCapture $capture): ?string
    {
        if (null === $capture) {
            return in_array($payPalOrder['status'] ?? null, self::UNAPPROVED_ORDER_STATUSES, true)
                ? null
                : 'PayPal did not capture the order.';
        }

        return in_array($capture->status(), self::REFUSED_CAPTURE_STATUSES, true)
            ? sprintf('PayPal reported the capture as %s.', $capture->status())
            : null;
    }

    /** @param array<string, mixed> $payPalOrder */
    private function isPayerAtTheBank(PaymentInterface $payment, array $payPalOrder): bool
    {
        return
            null !== RedirectPaymentSource::tryFrom(PayPalPaymentDetails::fromPayment($payment)->paymentSource()) &&
            self::PAYER_ACTION_REQUIRED_ORDER_STATUS === ($payPalOrder['status'] ?? null)
        ;
    }

    private function awaitPayer(PaymentRequestInterface $paymentRequest): void
    {
        $paymentRequest->setResponseData([self::RESPONSE_AWAITING_PAYER => true] + $paymentRequest->getResponseData());
    }

    private function cancelUnapproved(PaymentRequestInterface $paymentRequest): void
    {
        $paymentRequest->setResponseData([self::RESPONSE_REASON => 'The payer did not approve the PayPal order.']);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);
    }

    private function complete(PaymentRequestInterface $paymentRequest, PaymentInterface $payment): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS);
        }

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }
}
