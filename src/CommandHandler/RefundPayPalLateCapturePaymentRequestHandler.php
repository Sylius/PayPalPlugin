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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Command\RefundPayPalLateCapturePaymentRequest;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final class RefundPayPalLateCapturePaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const RESPONSE_REFUND_ID = 'refund_id';

    private const FAILED_REFUND_STATUSES = ['FAILED', 'CANCELLED'];

    private const CAPTURE_FULLY_REFUNDED = 'CAPTURE_FULLY_REFUNDED';

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly OrderDetailsApiInterface $orderDetailsApi,
        private readonly RefundPaymentApiInterface $refundPaymentApi,
        private readonly PayPalAuthAssertionGeneratorInterface $payPalAuthAssertionGenerator,
        private readonly RefundReferenceNumberProviderInterface $refundReferenceNumberProvider,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(RefundPayPalLateCapturePaymentRequest $refundPayPalLateCapturePaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($refundPayPalLateCapturePaymentRequest);

        if (PaymentRequestInterface::STATE_NEW === $paymentRequest->getState()) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        }

        if (PaymentRequestInterface::STATE_PROCESSING !== $paymentRequest->getState()) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $paymentRequest->getMethod();
        if (SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()) {
            $this->failWithReason($paymentRequest, 'The payment was not paid with PayPal.');

            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        $details = $payment->getDetails();
        $lateCapture = $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] ?? null;
        $payPalOrderId = (is_array($lateCapture) ? $lateCapture['order_id'] ?? null : null) ?? $details['paypal_order_id'] ?? null;

        if (
            !is_string($payPalOrderId) ||
            !is_array($lateCapture) ||
            !is_string($lateCapture['id'] ?? null) ||
            !is_int($lateCapture['amount'] ?? null) ||
            !is_string($lateCapture['currency_code'] ?? null)
        ) {
            $this->failWithReason($paymentRequest, 'The payment carries no late capture to refund.');

            return;
        }

        if (true === ($lateCapture['refunded'] ?? false)) {
            $this->complete($paymentRequest, $payment, $details, $lateCapture, is_string($lateCapture['refund_id'] ?? null) ? $lateCapture['refund_id'] : null);

            return;
        }

        try {
            $capture = PayPalCapture::fromPayPalOrder($this->orderDetailsApi->get($paymentMethod, $payPalOrderId));
            if (PayPalCapture::STATUS_REFUNDED === $capture?->status()) {
                $this->complete($paymentRequest, $payment, $details, $lateCapture, null);

                return;
            }

            $refund = $this->refundPaymentApi->refund(
                $paymentMethod,
                $lateCapture['id'],
                $this->payPalAuthAssertionGenerator->generate($paymentMethod),
                $this->refundReferenceNumberProvider->provide($payment),
                AmountUtils::toPayPalValue($lateCapture['amount'], $lateCapture['currency_code']),
                $lateCapture['currency_code'],
                (string) $paymentRequest->getId(),
            );
        } catch (PayPalApiException $exception) {
            if ($exception->hasIssue(self::CAPTURE_FULLY_REFUNDED)) {
                $this->complete($paymentRequest, $payment, $details, $lateCapture, null);

                return;
            }

            $this->failWithReason($paymentRequest, null === $exception->getStatusCode()
                ? 'PayPal could not be reached to refund the late capture.'
                : sprintf('PayPal rejected the refund: %s', $exception->getDescription()));

            return;
        }

        if (!is_string($refund['id'] ?? null) || in_array($refund['status'] ?? null, self::FAILED_REFUND_STATUSES, true)) {
            $this->failWithReason($paymentRequest, sprintf('PayPal reported the refund as %s.', is_string($refund['status'] ?? null) ? $refund['status'] : 'unknown'));

            return;
        }

        $this->complete($paymentRequest, $payment, $details, $lateCapture, $refund['id']);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, mixed> $lateCapture
     */
    private function complete(
        PaymentRequestInterface $paymentRequest,
        PaymentInterface $payment,
        array $details,
        array $lateCapture,
        ?string $refundId,
    ): void {
        $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] = array_merge($lateCapture, [
            'refunded' => true,
            'refund_id' => $refundId,
        ]);
        $payment->setDetails($details);

        $paymentRequest->setResponseData([self::RESPONSE_REFUND_ID => $refundId]);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }
}
