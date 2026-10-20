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
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenDeleted;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalVault;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class PaymentTokenWebhookProcessor implements WebhookProcessorInterface
{
    public const EVENT_TYPE_CREATED = 'VAULT.PAYMENT-TOKEN.CREATED';

    public const EVENT_TYPE_DELETED = 'VAULT.PAYMENT-TOKEN.DELETED';

    private const STATUS_VAULTED = 'VAULTED';

    public function __construct(
        private SettleablePaypalPaymentQueryInterface $paypalPaymentQuery,
        private ObjectManager $paymentManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function supports(string $eventType): bool
    {
        return in_array($eventType, [self::EVENT_TYPE_CREATED, self::EVENT_TYPE_DELETED], true);
    }

    public function process(array $payload): void
    {
        $resource = $payload['resource'] ?? null;
        $vaultId = is_array($resource) ? ($resource['id'] ?? null) : null;
        if (!is_array($resource) || !is_string($vaultId) || '' === $vaultId) {
            return;
        }

        if (self::EVENT_TYPE_DELETED === ($payload['event_type'] ?? null)) {
            $this->eventDispatcher->dispatch(new PayPalPaymentTokenDeleted($vaultId));

            return;
        }

        $this->recordCreatedToken($resource, $vaultId);
    }

    /** @param array<array-key, mixed> $resource */
    private function recordCreatedToken(array $resource, string $vaultId): void
    {
        $payPalOrderId = $resource['metadata']['order_id'] ?? null;
        if (!is_string($payPalOrderId) || '' === $payPalOrderId) {
            return;
        }

        try {
            $payment = $this->paypalPaymentQuery->getForSettlementByOrderId($payPalOrderId);
        } catch (PaymentNotFoundException) {
            return;
        }

        $details = PayPalPaymentDetails::fromPayment($payment);
        if (null !== $details->vaultId()) {
            return;
        }

        $customerId = $resource['customer']['id'] ?? null;
        $payment->setDetails($details->withVault(new PayPalVault(
            self::STATUS_VAULTED,
            $vaultId,
            is_string($customerId) ? $customerId : $details->vaultCustomerId(),
            $details->payerEmail(),
        ))->toArray());
        $this->paymentManager->flush();

        $this->eventDispatcher->dispatch(new PayPalPaymentTokenCreated($payment, $vaultId));
    }
}
