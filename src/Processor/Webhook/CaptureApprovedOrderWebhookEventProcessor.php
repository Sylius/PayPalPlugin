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

namespace Sylius\PayPalPlugin\Processor\Webhook;

use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CaptureApprovedOrderWebhookEventProcessor implements WebhookEventProcessorInterface
{
    public const EVENT_TYPE = 'CHECKOUT.ORDER.APPROVED';

    private const APPROVED_ORDER_STATUS = 'APPROVED';

    private const PAYPAL_ORDER_ACTIONS = [PaymentRequestInterface::ACTION_CAPTURE, PaymentRequestInterface::ACTION_AUTHORIZE];

    private const SETTLED_PAYMENT_STATES = [BasePaymentInterface::STATE_AUTHORIZED, BasePaymentInterface::STATE_COMPLETED];

    /** @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository */
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private PaymentRequestCommandProviderInterface $paymentRequestCommandProvider,
        private MessageBusInterface $commandBus,
        private PaymentSettlementProcessorInterface $paymentSettlementProcessor,
    ) {
    }

    public function process(string $eventType, array $resource, PaymentInterface $payment): void
    {
        if (
            self::APPROVED_ORDER_STATUS !== ($resource['status'] ?? null) ||
            in_array($payment->getState(), self::SETTLED_PAYMENT_STATES, true)
        ) {
            return;
        }

        $capturePaymentRequest = $this->awaitedCapturePaymentRequest($resource, $payment);
        if (null === $capturePaymentRequest) {
            $this->settlePreUpgradePayPalOrder($resource, $payment);

            return;
        }

        $this->commandBus->dispatch($this->paymentRequestCommandProvider->provide($capturePaymentRequest));
    }

    /** @param array<string, mixed> $resource */
    private function settlePreUpgradePayPalOrder(array $resource, PaymentInterface $payment): void
    {
        $payPalOrderId = $resource['id'] ?? null;
        if (
            !is_string($payPalOrderId) ||
            BasePaymentInterface::STATE_PROCESSING !== $payment->getState() ||
            $this->hasPaymentRequestCustomId($resource)
        ) {
            return;
        }

        $this->paymentSettlementProcessor->settle($payment, null, $payPalOrderId);
    }

    /** @param array<string, mixed> $resource */
    private function hasPaymentRequestCustomId(array $resource): bool
    {
        $purchaseUnits = $resource['purchase_units'] ?? [];
        foreach (is_array($purchaseUnits) ? $purchaseUnits : [] as $purchaseUnit) {
            $customId = is_array($purchaseUnit) ? ($purchaseUnit['custom_id'] ?? null) : null;
            if (is_string($customId) && Uuid::isValid($customId)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $resource */
    private function awaitedCapturePaymentRequest(array $resource, PaymentInterface $payment): ?PaymentRequestInterface
    {
        $purchaseUnits = $resource['purchase_units'] ?? [];
        foreach (is_array($purchaseUnits) ? $purchaseUnits : [] as $purchaseUnit) {
            $customId = is_array($purchaseUnit) ? ($purchaseUnit['custom_id'] ?? null) : null;
            if (!is_string($customId) || !Uuid::isValid($customId)) {
                continue;
            }

            $paymentRequest = $this->paymentRequestRepository->find($customId);
            if (
                null !== $paymentRequest &&
                PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState() &&
                $paymentRequest->getPayment() === $payment &&
                in_array($paymentRequest->getAction(), self::PAYPAL_ORDER_ACTIONS, true)
            ) {
                return $paymentRequest;
            }
        }

        return null;
    }
}
