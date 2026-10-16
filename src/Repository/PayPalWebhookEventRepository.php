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

namespace Sylius\PayPalPlugin\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;
use Sylius\PayPalPlugin\Entity\PayPalWebhookEventInterface;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;

/** @extends EntityRepository<PayPalWebhookEventInterface> */
class PayPalWebhookEventRepository extends EntityRepository implements PayPalWebhookEventRepositoryInterface
{
    private const CLAIM_SAVEPOINT = 'sylius_paypal_webhook_event_claim';

    public function claim(string $eventId, string $paymentMethodCode, string $eventType): bool
    {
        $connection = $this->getEntityManager()->getConnection();

        if (!$connection->isTransactionActive()) {
            throw new \LogicException('A webhook event can only be claimed inside the transaction that processes it, so a rollback releases the claim.');
        }

        $connection->createSavepoint(self::CLAIM_SAVEPOINT);

        try {
            $connection->insert($this->getClassMetadata()->getTableName(), [
                'event_id' => $eventId,
                'payment_method_code' => $paymentMethodCode,
                'event_type' => $eventType,
                'status' => PayPalWebhookEventStatus::Received->value,
                'created_at' => new \DateTime(),
            ], ['created_at' => Types::DATETIME_MUTABLE]);
        } catch (UniqueConstraintViolationException) {
            $connection->rollbackSavepoint(self::CLAIM_SAVEPOINT);

            return false;
        }

        $connection->releaseSavepoint(self::CLAIM_SAVEPOINT);

        return true;
    }

    public function markAs(string $eventId, string $paymentMethodCode, PayPalWebhookEventStatus $status): void
    {
        $updated = $this->getEntityManager()->getConnection()->update(
            $this->getClassMetadata()->getTableName(),
            ['status' => $status->value, 'updated_at' => new \DateTime()],
            ['event_id' => $eventId, 'payment_method_code' => $paymentMethodCode],
            ['updated_at' => Types::DATETIME_MUTABLE],
        );

        if (0 === (int) $updated) {
            throw new \LogicException(sprintf(
                'The PayPal webhook event "%s" of the payment method "%s" has not been claimed.',
                $eventId,
                $paymentMethodCode,
            ));
        }
    }

    public function removeCreatedBefore(\DateTimeInterface $date): int
    {
        /** @var int $removed */
        $removed = $this->createQueryBuilder('e')
            ->delete()
            ->andWhere('e.createdAt < :date')
            ->setParameter('date', $date, Types::DATETIME_MUTABLE)
            ->getQuery()
            ->execute()
        ;

        return $removed;
    }
}
