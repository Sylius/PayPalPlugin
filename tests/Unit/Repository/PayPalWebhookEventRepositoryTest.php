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

namespace Tests\Sylius\PayPalPlugin\Unit\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Entity\PayPalWebhookEvent;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepository;

final class PayPalWebhookEventRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;

    private PayPalWebhookEventRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createMock(Connection::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->connection);

        $classMetadata = new ClassMetadata(PayPalWebhookEvent::class);
        $classMetadata->setPrimaryTable(['name' => 'sylius_paypal_plugin_webhook_event']);

        $this->repository = new PayPalWebhookEventRepository($entityManager, $classMetadata);
    }

    public function test_it_claims_an_event_it_has_not_seen_yet_inside_a_savepoint_of_the_running_transaction(): void
    {
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->connection->expects(self::once())->method('createSavepoint');
        $this->connection
            ->expects(self::once())
            ->method('insert')
            ->with('sylius_paypal_plugin_webhook_event', self::callback(static fn (array $data): bool => 'WH-1' === $data['event_id'] && 'PAYPAL' === $data['payment_method_code'] && 'PAYMENT.CAPTURE.COMPLETED' === $data['event_type'] && 'received' === $data['status']))
        ;
        $this->connection->expects(self::once())->method('releaseSavepoint');
        $this->connection->expects(self::never())->method('rollbackSavepoint');

        self::assertTrue($this->repository->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED'));
    }

    public function test_it_keeps_the_running_transaction_usable_when_the_event_was_already_seen(): void
    {
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->connection->expects(self::once())->method('createSavepoint');
        $this->connection->method('insert')->willThrowException($this->createStub(UniqueConstraintViolationException::class));
        $this->connection->expects(self::once())->method('rollbackSavepoint');
        $this->connection->expects(self::never())->method('releaseSavepoint');

        self::assertFalse($this->repository->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED'));
    }

    public function test_it_does_not_claim_an_event_outside_a_transaction(): void
    {
        $this->connection->method('isTransactionActive')->willReturn(false);
        $this->connection->expects(self::never())->method('insert');

        $this->expectException(\LogicException::class);

        $this->repository->claim('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED');
    }

    public function test_it_marks_a_claimed_event_with_its_outcome(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('update')
            ->with(
                'sylius_paypal_plugin_webhook_event',
                self::callback(static fn (array $data): bool => 'processed' === $data['status'] && $data['updated_at'] instanceof \DateTimeInterface),
                ['event_id' => 'WH-1', 'payment_method_code' => 'PAYPAL'],
            )
            ->willReturn(1)
        ;

        $this->repository->markAs('WH-1', 'PAYPAL', PayPalWebhookEventStatus::Processed);
    }

    public function test_it_does_not_mark_an_event_that_has_not_been_claimed(): void
    {
        $this->connection->method('update')->willReturn(0);

        $this->expectException(\LogicException::class);

        $this->repository->markAs('WH-1', 'PAYPAL', PayPalWebhookEventStatus::Processed);
    }
}
