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
use Sylius\PayPalPlugin\Command\CaptureEndPaymentRequest;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;

final class CaptureEndPaymentRequestHandler
{
    use FailPaymentRequestTrait;

    private const UNAPPROVED_ORDER_STATUSES = ['CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED', 'VOIDED'];

    private const REFUSED_CAPTURE_STATUSES = [PayPalCapture::STATUS_DECLINED, PayPalCapture::STATUS_FAILED];

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly PaymentCaptureProcessorInterface $paymentCaptureProcessor,
        private readonly PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(CaptureEndPaymentRequest $captureEndPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($captureEndPaymentRequest);

        if (PaymentRequestInterface::STATE_PROCESSING !== $paymentRequest->getState()) {
            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();

        if (!PayPalPaymentDetails::fromPayment($payment)->hasPayPalOrderId()) {
            $this->failWithReason($paymentRequest, 'The payment carries no PayPal order id.');

            return;
        }

        $payPalOrder = $this->paymentCaptureProcessor->capture($payment);
        $capture = PayPalCapture::fromPayPalOrder($payPalOrder);

        if (null === $capture) {
            $this->endWithoutCapture($paymentRequest, $payPalOrder['status'] ?? null);

            return;
        }

        $this->endWithCapture($paymentRequest, $payment, $payPalOrder, $capture);
    }

    private function endWithoutCapture(PaymentRequestInterface $paymentRequest, mixed $payPalOrderStatus): void
    {
        if (!in_array($payPalOrderStatus, self::UNAPPROVED_ORDER_STATUSES, true)) {
            $this->failWithReason($paymentRequest, 'PayPal did not capture the order.');

            return;
        }

        $paymentRequest->setResponseData(['reason' => 'The payer did not approve the PayPal order.']);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);
    }

    /** @param array<string, mixed> $payPalOrder */
    private function endWithCapture(
        PaymentRequestInterface $paymentRequest,
        PaymentInterface $payment,
        array $payPalOrder,
        PayPalCapture $capture,
    ): void {
        $this->paymentSettlementProcessor->settle($payment, $payPalOrder);

        if (in_array($capture->status(), self::REFUSED_CAPTURE_STATUSES, true)) {
            $this->failWithReason($paymentRequest, sprintf('PayPal reported the capture as %s.', $capture->status()));

            return;
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS);
        }

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }
}
