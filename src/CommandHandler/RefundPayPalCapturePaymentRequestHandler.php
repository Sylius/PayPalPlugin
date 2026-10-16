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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Command\RefundPayPalCapturePaymentRequest;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final class RefundPayPalCapturePaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const RESPONSE_REFUND_ID = 'refund_id';

    public const RESPONSE_REFUND_STATUS = 'refund_status';

    public const REFUND_STATUS_COMPLETED = 'COMPLETED';

    public const REFUND_STATUS_PENDING = 'PENDING';

    private const CAPTURE_FULLY_REFUNDED = 'CAPTURE_FULLY_REFUNDED';

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly OrderDetailsApiInterface $orderDetailsApi,
        private readonly RefundPaymentApiInterface $refundPaymentApi,
        private readonly PayPalAuthAssertionGeneratorInterface $payPalAuthAssertionGenerator,
        private readonly RefundReferenceNumberProviderInterface $refundReferenceNumberProvider,
        private readonly PayPalPaymentRefundConfirmationProcessorInterface $refundConfirmationProcessor,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(RefundPayPalCapturePaymentRequest $refundPayPalCapturePaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($refundPayPalCapturePaymentRequest);

        if (PaymentRequestInterface::STATE_NEW === $paymentRequest->getState()) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        }

        if (PaymentRequestInterface::STATE_PROCESSING !== $paymentRequest->getState()) {
            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        $details = PayPalPaymentDetails::fromPayment($payment);

        if ($details->isRefunded()) {
            $this->confirm($paymentRequest, $payment, $details->refundId());

            return;
        }

        $payPalOrderId = $details->payPalOrderId();
        if (null === $payPalOrderId) {
            $this->failWithReason($paymentRequest, 'The payment carries no PayPal order id.');

            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $paymentRequest->getMethod();

        try {
            $capture = PayPalCapture::fromPayPalOrder($this->orderDetailsApi->get($paymentMethod, $payPalOrderId));
            if (null === $capture || null === $capture->id()) {
                $this->failWithReason($paymentRequest, 'The PayPal order has no capture to refund.');

                return;
            }

            if (PayPalCapture::STATUS_REFUNDED === $capture->status()) {
                $this->confirm($paymentRequest, $payment, null);

                return;
            }

            /** @var OrderInterface $order */
            $order = $payment->getOrder();
            $currencyCode = (string) $order->getCurrencyCode();

            $refund = $this->refundPaymentApi->refund(
                $paymentMethod,
                $capture->id(),
                $this->payPalAuthAssertionGenerator->generate($paymentMethod),
                $this->refundReferenceNumberProvider->provide($payment),
                AmountUtils::toPayPalValue((int) $payment->getAmount(), $currencyCode),
                $currencyCode,
                (string) $paymentRequest->getId(),
            );
        } catch (PayPalApiException $exception) {
            if ($exception->hasIssue(self::CAPTURE_FULLY_REFUNDED)) {
                $this->confirm($paymentRequest, $payment, null);

                return;
            }

            $this->failWithReason($paymentRequest, null === $exception->getStatusCode()
                ? 'PayPal could not be reached to refund the payment.'
                : sprintf('PayPal rejected the refund: %s', $exception->getDescription()));

            return;
        }

        $refundId = is_string($refund['id'] ?? null) ? $refund['id'] : null;
        $refundStatus = is_string($refund['status'] ?? null) ? $refund['status'] : null;

        if (null === $refundId || !in_array($refundStatus, [self::REFUND_STATUS_COMPLETED, self::REFUND_STATUS_PENDING], true)) {
            $this->failWithReason($paymentRequest, sprintf('PayPal reported the refund as %s.', $refundStatus ?? 'unknown'));

            return;
        }

        $paymentRequest->setResponseData([
            self::RESPONSE_REFUND_ID => $refundId,
            self::RESPONSE_REFUND_STATUS => $refundStatus,
        ]);

        if (self::REFUND_STATUS_COMPLETED === $refundStatus) {
            $this->confirm($paymentRequest, $payment, $refundId);
        }
    }

    private function confirm(PaymentRequestInterface $paymentRequest, PaymentInterface $payment, ?string $refundId): void
    {
        $this->refundConfirmationProcessor->confirm($payment, $refundId);

        if (PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState()) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
        }
    }
}
