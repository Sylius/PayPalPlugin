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

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\AuthorizeOrderApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderCheckerInterface;
use Sylius\PayPalPlugin\Checker\PreUpgradePayPalOrderCheckerInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class PreUpgradePayPalOrderSettlementProcessor implements PaymentSettlementProcessorInterface
{
    public const PAYPAL_ORDER_LIFETIME = 'PT72H';

    public const THREE_D_SECURE_RETRY = 'retry';

    public const THREE_D_SECURE_DECLINED = 'declined';

    private const ORDER_NOT_FOUND_ERROR = 'RESOURCE_NOT_FOUND';

    private const VOIDED_ORDER_STATUS = 'VOIDED';

    private const ISSUES_OF_AN_ORDER_ALREADY_SETTLED = ['ORDER_ALREADY_CAPTURED', 'ORDER_ALREADY_AUTHORIZED'];

    private const INSTRUMENT_DECLINED = 'INSTRUMENT_DECLINED';

    public function __construct(
        private PaymentSettlementProcessorInterface $decoratedPaymentSettlementProcessor,
        private PreUpgradePayPalOrderCheckerInterface $preUpgradePayPalOrderChecker,
        private ApprovedPayPalOrderCheckerInterface $approvedPayPalOrderChecker,
        private ApprovedPayPalOrderUpdaterInterface $approvedPayPalOrderUpdater,
        private OrderDetailsApiInterface $orderDetailsApi,
        private CompleteOrderApiInterface $completeOrderApi,
        private AuthorizeOrderApiInterface $authorizeOrderApi,
        private ThreeDSecureVerifierInterface $threeDSecureVerifier,
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $paymentManager,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function settle(PaymentInterface $payment, ?PayPalOrderDetails $payPalOrderDetails = null, ?string $payPalOrderId = null): void
    {
        if (null === $this->preUpgradePayPalOrderId($payment, $payPalOrderId)) {
            $this->decoratedPaymentSettlementProcessor->settle($payment, $payPalOrderDetails, $payPalOrderId);

            return;
        }

        $connection = $this->paymentManager->getConnection();
        $ownTransaction = !$connection->isTransactionActive();
        if ($ownTransaction) {
            $connection->beginTransaction();
        }

        try {
            $this->settleLocked($payment, $payPalOrderDetails, $payPalOrderId);
            if ($ownTransaction) {
                $this->paymentManager->flush();
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownTransaction) {
                $connection->rollBack();
            }
            $this->discardChanges($payment);

            throw $exception;
        }
    }

    private function discardChanges(PaymentInterface $payment): void
    {
        if (!$this->paymentManager->isOpen()) {
            return;
        }

        $order = $payment->getOrder();
        if (null !== $order && $this->paymentManager->contains($order)) {
            $this->paymentManager->refresh($order);

            return;
        }

        if ($this->paymentManager->contains($payment)) {
            $this->paymentManager->refresh($payment);
        }
    }

    private function settleLocked(PaymentInterface $payment, ?PayPalOrderDetails $payPalOrderDetails, ?string $payPalOrderId): void
    {
        $this->paymentManager->refresh($payment, LockMode::PESSIMISTIC_WRITE);

        $preUpgradePayPalOrderId = $this->preUpgradePayPalOrderId($payment, $payPalOrderId);
        if (null === $preUpgradePayPalOrderId) {
            return;
        }

        $paymentMethod = $this->paymentMethod($payment);

        try {
            $payPalOrderDetails ??= $this->orderDetailsApi->get($paymentMethod, $preUpgradePayPalOrderId);
        } catch (PayPalApiNotFoundException $exception) {
            if (self::ORDER_NOT_FOUND_ERROR !== $exception->getErrorName() || !$this->hasOutlivedItsPayPalOrder($payment)) {
                throw $exception;
            }

            $this->cancel($payment, $preUpgradePayPalOrderId, 'PayPal no longer returns it');

            return;
        }

        if (null === $payPalOrderDetails->capture() && null === $payPalOrderDetails->authorization()) {
            if ($payPalOrderDetails->isStatus(self::VOIDED_ORDER_STATUS)) {
                $this->cancel($payment, $preUpgradePayPalOrderId, 'PayPal voided it');

                return;
            }

            if ($this->approvedPayPalOrderChecker->isApproved($payPalOrderDetails, $this->paymentSource($payment, $payPalOrderDetails))) {
                try {
                    $this->threeDSecureVerifier->verify($payPalOrderDetails);
                } catch (ThreeDSecureAuthenticationFailedException $exception) {
                    $this->endWithFailedAuthentication($payment, $preUpgradePayPalOrderId, $exception->isRetryable());

                    return;
                }

                $this->approvedPayPalOrderUpdater->update($payment);
                $settledPayPalOrderDetails = $this->settledAtPayPal($paymentMethod, $payPalOrderDetails);
                if (null === $settledPayPalOrderDetails) {
                    $this->end($payment, PaymentTransitions::TRANSITION_FAIL, $preUpgradePayPalOrderId, 'PayPal declined the payment method of the buyer');

                    return;
                }

                $payPalOrderDetails = $settledPayPalOrderDetails;
            }
        }

        $this->decoratedPaymentSettlementProcessor->settle($payment, $payPalOrderDetails, $preUpgradePayPalOrderId);
    }

    private function preUpgradePayPalOrderId(PaymentInterface $payment, ?string $payPalOrderId): ?string
    {
        $paymentPayPalOrderId = PayPalPaymentDetails::fromPayment($payment)->payPalOrderId();
        if (
            (null !== $payPalOrderId && $payPalOrderId !== $paymentPayPalOrderId) ||
            !$this->preUpgradePayPalOrderChecker->isAwaitingPreUpgradePayPalOrder($payment)
        ) {
            return null;
        }

        return $paymentPayPalOrderId;
    }

    private function paymentSource(PaymentInterface $payment, PayPalOrderDetails $payPalOrderDetails): string
    {
        $paymentSource = $payment->getDetails()[PayPalPaymentDetails::PAYMENT_SOURCE] ?? null;
        if (is_string($paymentSource) && '' !== $paymentSource) {
            return $paymentSource;
        }

        $orderPaymentSource = array_key_first($payPalOrderDetails->paymentSource());

        return is_string($orderPaymentSource) ? $orderPaymentSource : PayPalPaymentDetails::fromPayment($payment)->paymentSource();
    }

    private function hasOutlivedItsPayPalOrder(PaymentInterface $payment): bool
    {
        $lastChange = $payment->getUpdatedAt() ?? $payment->getCreatedAt();
        if (null === $lastChange) {
            return false;
        }

        return \DateTimeImmutable::createFromInterface($lastChange)->add(new \DateInterval(self::PAYPAL_ORDER_LIFETIME)) <= $this->clock->now();
    }

    private function settledAtPayPal(PaymentMethodInterface $paymentMethod, PayPalOrderDetails $payPalOrderDetails): ?PayPalOrderDetails
    {
        $payPalOrderId = $payPalOrderDetails->id();
        $requestId = sprintf('sylius-paypal-pre-upgrade-%s', $payPalOrderId);

        try {
            if (PayPalOrder::INTENT_AUTHORIZE === $payPalOrderDetails->intent()) {
                $this->authorizeOrderApi->authorize($paymentMethod, $payPalOrderId, $requestId);
            } else {
                $this->completeOrderApi->complete($paymentMethod, $payPalOrderId, $requestId);
            }
        } catch (PayPalApiRequestException $exception) {
            if ($exception->hasIssue(self::INSTRUMENT_DECLINED)) {
                return null;
            }

            if ([] === array_intersect($exception->getIssues(), self::ISSUES_OF_AN_ORDER_ALREADY_SETTLED)) {
                throw $exception;
            }
        }

        return $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
    }

    private function endWithFailedAuthentication(PaymentInterface $payment, string $payPalOrderId, bool $retryable): void
    {
        $payment->setDetails(PayPalPaymentDetails::fromPayment($payment)
            ->withThreeDSecureFailure($retryable ? self::THREE_D_SECURE_RETRY : self::THREE_D_SECURE_DECLINED)
            ->toArray());

        $this->end(
            $payment,
            $retryable ? PaymentTransitions::TRANSITION_CANCEL : PaymentTransitions::TRANSITION_FAIL,
            $payPalOrderId,
            $retryable ? 'the 3D Secure authentication of the card did not finish' : 'the 3D Secure authentication refused the card',
        );
    }

    private function cancel(PaymentInterface $payment, string $payPalOrderId, string $reason): void
    {
        $this->end($payment, PaymentTransitions::TRANSITION_CANCEL, $payPalOrderId, $reason);
    }

    private function end(PaymentInterface $payment, string $transition, string $payPalOrderId, string $reason): void
    {
        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            return;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
        $this->paymentManager->flush();

        $this->logger->warning(sprintf(
            'Payment #%s was %s: PayPal order %s it awaited since before the upgrade to 3.0 was never paid and %s.',
            (string) $payment->getId(),
            (string) $payment->getState(),
            $payPalOrderId,
            $reason,
        ));
    }

    private function paymentMethod(PaymentInterface $payment): PaymentMethodInterface
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        return $paymentMethod;
    }
}
