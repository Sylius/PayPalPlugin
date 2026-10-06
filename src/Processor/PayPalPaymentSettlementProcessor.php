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
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;

final readonly class PayPalPaymentSettlementProcessor implements PaymentSettlementProcessorInterface
{
    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private OrderDetailsApiInterface $orderDetailsApi,
        private StateMachineInterface $stateMachine,
        private ObjectManager $paymentManager,
        private LoggerInterface $logger,
    ) {
    }

    public function settle(PaymentInterface $payment, ?array $payPalOrderDetails = null): void
    {
        $details = PayPalPaymentDetails::fromPayment($payment);
        $payPalOrderId = (string) $details->payPalOrderId();

        if (!$details->hasPayPalOrderId()) {
            $this->logger->warning(sprintf(
                'Payment #%s cannot be settled: it carries no PayPal order id.',
                (string) $payment->getId(),
            ));

            return;
        }

        $payPalOrderDetails ??= $this->fetchOrderDetails($payment, $payPalOrderId);
        $capture = PayPalCapture::fromPayPalOrder($payPalOrderDetails);
        if (null === $capture) {
            return;
        }

        $transition = match ($capture->status()) {
            PayPalCapture::STATUS_COMPLETED => PaymentTransitions::TRANSITION_COMPLETE,
            PayPalCapture::STATUS_DECLINED, PayPalCapture::STATUS_FAILED => PaymentTransitions::TRANSITION_FAIL,
            default => null,
        };

        if (null === $transition) {
            return;
        }

        $state = PaymentTransitions::TRANSITION_COMPLETE === $transition
            ? PaymentInterface::STATE_COMPLETED
            : PaymentInterface::STATE_FAILED;

        if ($state === $payment->getState()) {
            return;
        }

        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            $this->logger->error(sprintf(
                'PayPal order %s settled as %s, but payment #%s is %s and cannot be %s. Reconcile it by hand.',
                $payPalOrderId,
                $capture->status(),
                (string) $payment->getId(),
                (string) $payment->getState(),
                $state,
            ));

            return;
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
        $this->paymentManager->flush();
    }

    /** @return array<string, mixed> */
    private function fetchOrderDetails(PaymentInterface $payment, string $payPalOrderId): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        return $this->orderDetailsApi->get($this->authorizeClientApi->authorize($paymentMethod), $payPalOrderId);
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
