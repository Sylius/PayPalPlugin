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

use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;

final readonly class PayPalPaymentSettlementProcessor implements PaymentSettlementProcessorInterface
{
    public const LATE_CAPTURE = 'paypal_late_capture';

    public function __construct(
        private OrderDetailsApiInterface $orderDetailsApi,
        private StateMachineInterface $stateMachine,
        private ObjectManager $paymentManager,
        private LoggerInterface $logger,
    ) {
    }

    public function settle(PaymentInterface $payment, ?array $payPalOrderDetails = null, ?string $payPalOrderId = null): void
    {
        $details = PayPalPaymentDetails::fromPayment($payment);
        $payPalOrderId ??= $details->payPalOrderId();
        if (null === $payPalOrderId || '' === $payPalOrderId) {
            $this->logger->warning(sprintf(
                'Payment #%s cannot be settled: it carries no PayPal order id.',
                (string) $payment->getId(),
            ));

            return;
        }

        $payPalOrderDetails ??= $this->fetchOrderDetails($payment, $payPalOrderId);
        $capture = PayPalCapture::fromPayPalOrder($payPalOrderDetails);

        $authorization = PayPalAuthorization::fromPayPalOrder($payPalOrderDetails);
        $authorizationRecorded = null !== $authorization && $payPalOrderId === $details->payPalOrderId();
        if ($authorizationRecorded) {
            $details = $details->withAuthorization($authorization);
            $payment->setDetails($details->toArray());
        }

        $settled = match (true) {
            null !== $capture => $this->settleCapture($payment, $details, $payPalOrderId, $capture),
            $authorizationRecorded => $this->settleAuthorization($payment, $authorization),
            default => false,
        };

        if ($settled || $authorizationRecorded) {
            $this->paymentManager->flush();
        }
    }

    private function settleCapture(PaymentInterface $payment, PayPalPaymentDetails $details, string $payPalOrderId, PayPalCapture $capture): bool
    {
        $transition = match ($capture->status()) {
            PayPalCapture::STATUS_COMPLETED => PaymentTransitions::TRANSITION_COMPLETE,
            PayPalCapture::STATUS_DECLINED, PayPalCapture::STATUS_FAILED => PaymentTransitions::TRANSITION_FAIL,
            default => null,
        };

        if (null === $transition) {
            return false;
        }

        if ($details->isSupersededPayPalOrder($payPalOrderId)) {
            if (PaymentTransitions::TRANSITION_COMPLETE !== $transition) {
                return false;
            }

            if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
                return $this->keepLateCapture($payment, $payPalOrderId, $capture, $transition, PaymentInterface::STATE_COMPLETED);
            }

            $details = $details->withSupersededPayPalOrderRestored($payPalOrderId);
        }

        $state = PaymentTransitions::TRANSITION_COMPLETE === $transition
            ? PaymentInterface::STATE_COMPLETED
            : PaymentInterface::STATE_FAILED;

        if ($state === $payment->getState()) {
            return false;
        }

        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            if (PaymentTransitions::TRANSITION_FAIL === $transition && PaymentInterface::STATE_CANCELLED === $payment->getState()) {
                return false;
            }

            return $this->keepLateCapture($payment, $payPalOrderId, $capture, $transition, $state);
        }

        $settledDetails = $details
            ->clearPayerAction()
            ->withStatus(PaymentTransitions::TRANSITION_COMPLETE === $transition
                ? PayPalPaymentStatus::Completed
                : PayPalPaymentStatus::Processing)
        ;
        if (null !== $capture->id()) {
            $settledDetails = $settledDetails->withTransactionId($capture->id());
        }

        $payment->setDetails(
            $this->withMismatchedCapture($settledDetails, $payment, $payPalOrderId, $capture)->toArray(),
        );

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);

        return true;
    }

    private function settleAuthorization(PaymentInterface $payment, PayPalAuthorization $authorization): bool
    {
        $transition = match (true) {
            $authorization->isStatus(PayPalAuthorization::STATUS_CREATED) => PaymentTransitions::TRANSITION_AUTHORIZE,
            $authorization->isStatus(PayPalAuthorization::STATUS_DENIED) => PaymentTransitions::TRANSITION_FAIL,
            $authorization->isReleased() && PaymentInterface::STATE_AUTHORIZED === $payment->getState() => PaymentTransitions::TRANSITION_CANCEL,
            default => null,
        };

        if (null === $transition || !$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            return false;
        }

        if (PaymentTransitions::TRANSITION_AUTHORIZE === $transition) {
            $payment->setDetails(PayPalPaymentDetails::fromPayment($payment)->withStatus(PayPalPaymentStatus::Authorized)->toArray());
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);

        return true;
    }

    private function keepLateCapture(
        PaymentInterface $payment,
        string $payPalOrderId,
        PayPalCapture $capture,
        string $transition,
        string $state,
    ): bool {
        $details = $payment->getDetails();
        $lateCapture = $details[self::LATE_CAPTURE] ?? null;
        if (is_array($lateCapture) && ($lateCapture['id'] ?? null) === $capture->id()) {
            return false;
        }

        $kept = PaymentTransitions::TRANSITION_COMPLETE === $transition &&
            null !== $capture->id() &&
            null !== $capture->amount() &&
            null !== $capture->currencyCode();

        $this->logger->error(sprintf(
            'PayPal order %s settled as %s, but payment #%s is %s and cannot be %s. %s',
            $payPalOrderId,
            $capture->status(),
            (string) $payment->getId(),
            (string) $payment->getState(),
            $state,
            $kept ? 'The capture is kept on the payment to be refunded from the admin panel.' : 'Reconcile it by hand.',
        ));

        if (!$kept) {
            return false;
        }

        $details[self::LATE_CAPTURE] = [
            'id' => $capture->id(),
            'order_id' => $payPalOrderId,
            'amount' => $capture->amount(),
            'currency_code' => $capture->currencyCode(),
        ];
        $payment->setDetails($details);

        return true;
    }

    /** @return array<string, mixed> */
    private function fetchOrderDetails(PaymentInterface $payment, string $payPalOrderId): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        return $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
    }

    private function withMismatchedCapture(
        PayPalPaymentDetails $details,
        PaymentInterface $payment,
        string $payPalOrderId,
        PayPalCapture $capture,
    ): PayPalPaymentDetails {
        if ($capture->amount() === $payment->getAmount() && $capture->currencyCode() === $payment->getCurrencyCode()) {
            return $details;
        }

        $this->logger->error(sprintf(
            'PayPal order %s captured %s %s while payment #%s expects %s %s.',
            $payPalOrderId,
            (string) ($capture->amount() ?? 'nothing'),
            $capture->currencyCode() ?? '?',
            (string) $payment->getId(),
            (string) $payment->getAmount(),
            (string) $payment->getCurrencyCode(),
        ));

        return $details->withCapturedAmount($capture->amount(), $capture->currencyCode());
    }
}
