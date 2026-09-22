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
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final readonly class PayPalPaymentSettlementProcessor implements PaymentSettlementProcessorInterface
{
    public const CAPTURE_STATUS_COMPLETED = 'COMPLETED';

    public const CAPTURE_STATUS_DECLINED = 'DECLINED';

    public const CAPTURE_STATUS_FAILED = 'FAILED';

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
        $details = $payment->getDetails();
        $payPalOrderId = $details['paypal_order_id'] ?? null;

        if (!is_string($payPalOrderId) || '' === $payPalOrderId) {
            $this->logger->warning(sprintf(
                'Payment #%s cannot be settled: it carries no PayPal order id.',
                (string) $payment->getId(),
            ));

            return;
        }

        $payPalOrderDetails ??= $this->fetchOrderDetails($payment, $payPalOrderId);
        $capture = $payPalOrderDetails['purchase_units'][0]['payments']['captures'][0] ?? [];

        $transition = match ($capture['status'] ?? null) {
            self::CAPTURE_STATUS_COMPLETED => PaymentTransitions::TRANSITION_COMPLETE,
            self::CAPTURE_STATUS_DECLINED, self::CAPTURE_STATUS_FAILED => PaymentTransitions::TRANSITION_FAIL,
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
                (string) $capture['status'],
                (string) $payment->getId(),
                (string) $payment->getState(),
                $state,
            ));

            return;
        }

        $this->verifyAmount($payment, $payPalOrderId, $capture);

        $payment->setDetails(array_merge($details, array_filter([
            'status' => PaymentTransitions::TRANSITION_COMPLETE === $transition
                ? StatusAction::STATUS_COMPLETED
                : StatusAction::STATUS_PROCESSING,
            'transaction_id' => $capture['id'] ?? null,
        ], static fn (mixed $value): bool => null !== $value)));

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

    /** @param array<string, mixed> $capture */
    private function verifyAmount(PaymentInterface $payment, string $payPalOrderId, array $capture): void
    {
        $amount = $capture['amount'] ?? [];
        $captured = isset($amount['value']) ? (int) round(((float) $amount['value']) * 100) : null;
        $currencyCode = $amount['currency_code'] ?? null;

        if ($captured === $payment->getAmount() && $currencyCode === $payment->getCurrencyCode()) {
            return;
        }

        $this->logger->error(sprintf(
            'PayPal order %s captured %s %s while payment #%s expects %s %s.',
            $payPalOrderId,
            (string) ($amount['value'] ?? 'nothing'),
            (string) ($currencyCode ?? '?'),
            (string) $payment->getId(),
            (string) $payment->getAmount(),
            (string) $payment->getCurrencyCode(),
        ));
    }
}
