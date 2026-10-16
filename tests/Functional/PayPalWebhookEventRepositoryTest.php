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

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\PayPalPlugin\Entity\PayPalWebhookEvent;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepository;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepositoryInterface;

final class PayPalWebhookEventRepositoryTest extends JsonApiTestCase
{
    public function test_it_lets_an_event_be_claimed_only_once_per_payment_method(): void
    {
        $this->purgeDatabase();

        self::assertTrue($this->claimInTransaction('WH-1', 'PAYPAL'));
        self::assertFalse($this->claimInTransaction('WH-1', 'PAYPAL'));
        self::assertTrue($this->claimInTransaction('WH-1', 'PAYPAL_OTHER'));
        self::assertTrue($this->claimInTransaction('WH-2', 'PAYPAL'));

        self::assertSame(PayPalWebhookEventStatus::Received->value, $this->event('WH-1', 'PAYPAL')['status'] ?? null);
    }

    public function test_it_does_not_let_an_event_be_claimed_outside_a_transaction(): void
    {
        $this->purgeDatabase();

        try {
            $this->repository()->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED');
            self::fail('Claiming an event outside a transaction must be refused.');
        } catch (\LogicException) {
        }

        self::assertNull($this->event('WH-1', 'PAYPAL'));
    }

    public function test_it_releases_the_claim_when_the_transaction_that_made_it_is_rolled_back(): void
    {
        $this->purgeDatabase();

        $this->connection()->beginTransaction();
        self::assertTrue($this->repository()->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED'));
        $this->connection()->rollBack();

        self::assertTrue($this->claimInTransaction('WH-1', 'PAYPAL'));
    }

    public function test_it_keeps_the_transaction_usable_after_refusing_a_claim(): void
    {
        $this->purgeDatabase();
        $this->claimInTransaction('WH-1', 'PAYPAL');

        $this->connection()->beginTransaction();
        self::assertFalse($this->repository()->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED'));
        self::assertTrue($this->repository()->claim('WH-2', 'PAYPAL', 'PAYMENT.CAPTURE.REFUNDED'));
        $this->connection()->commit();

        self::assertNotNull($this->event('WH-2', 'PAYPAL'));
    }

    public function test_a_parallel_claim_of_the_same_event_waits_for_the_first_one_and_is_then_refused(): void
    {
        $this->purgeDatabase();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $parallelConnection = DriverManager::getConnection($this->connection()->getParams(), $this->connection()->getConfiguration());
        $parallelRepository = new PayPalWebhookEventRepository(
            new EntityManager($parallelConnection, $entityManager->getConfiguration()),
            $entityManager->getClassMetadata(PayPalWebhookEvent::class),
        );
        $parallelConnection->executeStatement(
            $parallelConnection->getDatabasePlatform() instanceof PostgreSQLPlatform
                ? "SET lock_timeout = '1s'"
                : 'SET SESSION innodb_lock_wait_timeout = 1',
        );

        $this->connection()->beginTransaction();
        self::assertTrue($this->repository()->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED'));

        $parallelConnection->beginTransaction();

        try {
            $parallelRepository->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED');
            self::fail('A parallel claim of an event another transaction is processing must wait for it.');
        } catch (DriverException $exception) {
            self::assertTrue($exception instanceof LockWaitTimeoutException || '55P03' === $exception->getSQLState());
        }
        $parallelConnection->rollBack();

        $this->connection()->commit();

        self::assertFalse($parallelConnection->transactional(
            static fn (): bool => $parallelRepository->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED'),
        ));
        $parallelConnection->close();
    }

    public function test_it_marks_an_event_with_its_outcome(): void
    {
        $this->purgeDatabase();
        $this->claimInTransaction('WH-1', 'PAYPAL');

        $this->repository()->markAs('WH-1', 'PAYPAL', PayPalWebhookEventStatus::Ignored);

        $event = $this->event('WH-1', 'PAYPAL');
        self::assertSame(PayPalWebhookEventStatus::Ignored->value, $event['status'] ?? null);
        self::assertNotNull($event['updated_at'] ?? null);
    }

    public function test_it_does_not_mark_an_event_that_has_not_been_claimed(): void
    {
        $this->purgeDatabase();
        $this->claimInTransaction('WH-1', 'PAYPAL');

        $this->expectException(\LogicException::class);

        $this->repository()->markAs('WH-1', 'PAYPAL_OTHER', PayPalWebhookEventStatus::Processed);
    }

    public function test_it_removes_only_events_received_before_the_given_date(): void
    {
        $this->purgeDatabase();
        $this->claimInTransaction('WH-OLD', 'PAYPAL');
        $this->claimInTransaction('WH-NEW', 'PAYPAL');
        $this->connection()->executeStatement(
            'UPDATE sylius_paypal_plugin_webhook_event SET created_at = :createdAt WHERE event_id = :eventId',
            ['createdAt' => (new \DateTime('-31 days'))->format('Y-m-d H:i:s'), 'eventId' => 'WH-OLD'],
        );

        self::assertSame(1, $this->repository()->removeCreatedBefore(new \DateTime('-30 days')));

        self::assertNull($this->event('WH-OLD', 'PAYPAL'));
        self::assertNotNull($this->event('WH-NEW', 'PAYPAL'));
    }

    private function claimInTransaction(string $eventId, string $paymentMethodCode): bool
    {
        return $this->connection()->transactional(
            fn (): bool => $this->repository()->claim($eventId, $paymentMethodCode, 'PAYMENT.CAPTURE.COMPLETED'),
        );
    }

    /** @return array<string, mixed>|null */
    private function event(string $eventId, string $paymentMethodCode): ?array
    {
        $event = $this->connection()->fetchAssociative(
            'SELECT * FROM sylius_paypal_plugin_webhook_event WHERE event_id = :eventId AND payment_method_code = :paymentMethodCode',
            ['eventId' => $eventId, 'paymentMethodCode' => $paymentMethodCode],
        );

        return false === $event ? null : $event;
    }

    private function repository(): PayPalWebhookEventRepositoryInterface
    {
        /** @var PayPalWebhookEventRepositoryInterface $repository */
        $repository = self::getContainer()->get('sylius_paypal.repository.webhook_event');

        return $repository;
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }
}
