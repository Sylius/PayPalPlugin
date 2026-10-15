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
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\CommandHandler\CapturePayPalOrderPaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventCaptureFailedException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventTooEarlyException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CaptureApprovedOrderWebhookEventProcessor implements WebhookEventProcessorInterface
{
    public const EVENT_TYPE = 'CHECKOUT.ORDER.APPROVED';

    private const APPROVED_ORDER_STATUS = 'APPROVED';

    /** @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository */
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private PaymentRequestCommandProviderInterface $paymentRequestCommandProvider,
        private MessageBusInterface $commandBus,
        private int $captureDelay,
    ) {
    }

    public function process(string $eventType, array $resource, PaymentInterface $payment): void
    {
        if (
            self::APPROVED_ORDER_STATUS !== ($resource['status'] ?? null) ||
            PaymentInterface::STATE_COMPLETED === $payment->getState()
        ) {
            return;
        }

        $capturePaymentRequest = $this->awaitedCapturePaymentRequest($resource, $payment);
        if (null === $capturePaymentRequest) {
            return;
        }

        $retryAfter = $this->approvedAt($resource) + $this->captureDelay - (new \DateTimeImmutable())->getTimestamp();
        if ($retryAfter > 0) {
            throw new PayPalWebhookEventTooEarlyException($eventType, $retryAfter);
        }

        $this->commandBus->dispatch($this->paymentRequestCommandProvider->provide($capturePaymentRequest));

        if (PaymentRequestInterface::STATE_FAILED === $capturePaymentRequest->getState()) {
            throw new PayPalWebhookEventCaptureFailedException(
                $eventType,
                (string) $capturePaymentRequest->getId(),
                (string) ($capturePaymentRequest->getResponseData()[CapturePayPalOrderPaymentRequestHandler::RESPONSE_REASON] ?? 'unknown reason'),
            );
        }
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
                PaymentRequestInterface::ACTION_CAPTURE === $paymentRequest->getAction() &&
                PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState() &&
                $paymentRequest->getPayment() === $payment
            ) {
                return $paymentRequest;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $resource */
    private function approvedAt(array $resource): int
    {
        foreach (['update_time', 'create_time'] as $field) {
            $time = $resource[$field] ?? null;
            if (is_string($time) && false !== $timestamp = strtotime($time)) {
                return $timestamp;
            }
        }

        return (new \DateTimeImmutable())->getTimestamp();
    }
}
