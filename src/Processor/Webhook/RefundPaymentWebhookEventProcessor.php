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

use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;

final readonly class RefundPaymentWebhookEventProcessor implements WebhookEventProcessorInterface
{
    public const REFUNDED_EVENT_TYPE = 'PAYMENT.CAPTURE.REFUNDED';

    public const REVERSED_EVENT_TYPE = 'PAYMENT.CAPTURE.REVERSED';

    public function __construct(
        private StateMachineInterface $stateMachine,
        private ObjectManager $paymentManager,
        private LoggerInterface $logger,
    ) {
    }

    public function process(string $eventType, array $resource, PaymentInterface $payment): void
    {
        $captureId = $this->captureId($resource);

        if ($this->markLateCaptureRefunded($payment, $captureId, (string) $resource['id'])) {
            return;
        }

        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)) {
            return;
        }

        if (self::REVERSED_EVENT_TYPE === $eventType) {
            $this->logger->warning(sprintf(
                'PayPal reversed capture %s of payment #%s; the payment is marked as refunded.',
                $captureId,
                (string) $payment->getId(),
            ));
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        $this->paymentManager->flush();
    }

    private function markLateCaptureRefunded(PaymentInterface $payment, string $captureId, string $refundId): bool
    {
        $details = $payment->getDetails();
        $lateCapture = $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] ?? null;
        if (!is_array($lateCapture) || $captureId !== ($lateCapture['id'] ?? null)) {
            return false;
        }

        if (true !== ($lateCapture['refunded'] ?? false)) {
            $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] = array_merge($lateCapture, [
                'refunded' => true,
                'refund_id' => $refundId,
            ]);
            $payment->setDetails($details);
            $this->paymentManager->flush();
        }

        return true;
    }

    /** @param array<string, mixed> $resource */
    private function captureId(array $resource): string
    {
        $links = $resource['links'] ?? [];
        foreach (is_array($links) ? $links : [] as $link) {
            if (is_array($link) && 'up' === ($link['rel'] ?? null) && is_string($link['href'] ?? null)) {
                return basename((string) parse_url($link['href'], \PHP_URL_PATH));
            }
        }

        throw new PayPalWrongDataException();
    }
}
