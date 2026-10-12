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
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;

final readonly class RefundOrderWebhookProcessor implements WebhookProcessorInterface
{
    public const EVENT_TYPE = 'PAYMENT.CAPTURE.REFUNDED';

    public function __construct(
        private PayPalRefundDataProviderInterface $payPalRefundDataProvider,
        private PaypalPaymentQueryInterface $paypalPaymentQuery,
        private StateMachineInterface $stateMachine,
        private ObjectManager $paymentManager,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return self::EVENT_TYPE === $eventType;
    }

    public function process(array $payload): void
    {
        $refundData = $this->payPalRefundDataProvider->provide($this->payPalOrderUrl($payload));

        try {
            $payment = $this->paypalPaymentQuery->getForRefundingByOrderId((string) $refundData['id']);
        } catch (PaymentNotFoundException) {
            return;
        }

        if (
            null !== $payment &&
            $this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)
        ) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        }

        $this->paymentManager->flush();
    }

    /** @param array<string, mixed> $payload */
    private function payPalOrderUrl(array $payload): string
    {
        /** @var array<array{rel?: string, href?: string}> $links */
        $links = $payload['resource']['links'] ?? [];

        foreach ($links as $link) {
            if ('up' === ($link['rel'] ?? null) && isset($link['href'])) {
                return (string) $link['href'];
            }
        }

        throw new PayPalWrongDataException();
    }
}
