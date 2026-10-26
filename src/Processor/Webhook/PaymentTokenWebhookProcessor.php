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

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenDeleted;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalVault;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Repository\Query\VaultedPaypalPaymentQueryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class PaymentTokenWebhookProcessor implements WebhookProcessorInterface
{
    public const EVENT_TYPE_CREATED = 'VAULT.PAYMENT-TOKEN.CREATED';

    public const EVENT_TYPE_DELETED = 'VAULT.PAYMENT-TOKEN.DELETED';

    private const STATUS_VAULTED = 'VAULTED';

    public function __construct(
        private SettleablePaypalPaymentQueryInterface&VaultedPaypalPaymentQueryInterface $paypalPaymentQuery,
        private EntityManagerInterface $paymentManager,
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
            $this->forgetDeletedToken($vaultId);

            return;
        }

        $this->recordCreatedToken($resource, $vaultId);
    }

    private function forgetDeletedToken(string $vaultId): void
    {
        $payments = $this->paypalPaymentQuery->findByVaultId($vaultId);
        foreach ($payments as $payment) {
            $payment->setDetails(PayPalPaymentDetails::fromPayment($payment)->withVaultDeleted()->toArray());
        }

        if ([] !== $payments) {
            $this->paymentManager->flush();
        }

        $this->eventDispatcher->dispatch(new PayPalPaymentTokenDeleted($vaultId));
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

        $customerId = $resource['customer']['id'] ?? null;
        $recorded = $this->paymentManager->wrapInTransaction(
            fn (): bool => $this->recordTokenOnLockedPayment($payment, $vaultId, is_string($customerId) ? $customerId : null),
        );

        if ($recorded) {
            $this->eventDispatcher->dispatch(new PayPalPaymentTokenCreated($payment, $vaultId));
        }
    }

    private function recordTokenOnLockedPayment(PaymentInterface $payment, string $vaultId, ?string $customerId): bool
    {
        $this->paymentManager->refresh($payment, LockMode::PESSIMISTIC_WRITE);

        $details = PayPalPaymentDetails::fromPayment($payment);
        if (null !== $details->vaultId()) {
            return false;
        }

        $payment->setDetails($details->withVault(new PayPalVault(
            self::STATUS_VAULTED,
            $vaultId,
            $customerId ?? $details->vaultCustomerId(),
            $details->payerEmail(),
        ))->toArray());

        return true;
    }
}
