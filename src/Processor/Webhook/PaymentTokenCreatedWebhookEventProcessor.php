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
use Sylius\PayPalPlugin\Command\AnnouncePayPalPaymentToken;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalVault;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class PaymentTokenCreatedWebhookEventProcessor implements PayloadResourceWebhookEventProcessorInterface
{
    public const EVENT_TYPE = 'VAULT.PAYMENT-TOKEN.CREATED';

    private const STATUS_VAULTED = 'VAULTED';

    public function __construct(
        private EntityManagerInterface $paymentManager,
        private MessageBusInterface $commandBus,
    ) {
    }

    public function process(string $eventType, array $resource, PaymentInterface $payment): void
    {
        $vaultId = $resource['id'] ?? null;
        if (!is_string($vaultId) || '' === $vaultId) {
            return;
        }

        $this->paymentManager->refresh($payment, LockMode::PESSIMISTIC_WRITE);

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

        $this->commandBus->dispatch(
            new AnnouncePayPalPaymentToken($payment->getId(), $vaultId),
            [new DispatchAfterCurrentBusStamp()],
        );
    }
}
