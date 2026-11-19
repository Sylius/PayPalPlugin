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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenDeleted;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Repository\Query\VaultedPaypalPaymentQueryInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class PaymentTokenDeletedWebhookEventProcessor implements PayloadResourceWebhookEventProcessorInterface
{
    public const EVENT_TYPE = 'VAULT.PAYMENT-TOKEN.DELETED';

    public function __construct(
        private VaultedPaypalPaymentQueryInterface $paypalPaymentQuery,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function process(string $eventType, array $resource, PaymentInterface $payment): void
    {
        $vaultId = $resource['id'] ?? null;
        if (!is_string($vaultId) || '' === $vaultId) {
            return;
        }

        foreach ($this->paypalPaymentQuery->findByVaultId($vaultId) as $vaultedPayment) {
            $vaultedPayment->setDetails(PayPalPaymentDetails::fromPayment($vaultedPayment)->withVaultDeleted()->toArray());
        }

        $this->eventBus->dispatch(
            new PayPalPaymentTokenDeleted($vaultId),
            [new DispatchAfterCurrentBusStamp()],
        );
    }
}
