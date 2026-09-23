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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Console\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Console\Command\SendShipmentTrackingCommand;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\PackageTracking\Processor\ShipmentTrackingProcessorInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SendShipmentTrackingCommandTest extends TestCase
{
    private MockObject&ShipmentTrackingRepositoryInterface $repository;

    private MockObject&ShipmentTrackingProcessorInterface $processor;

    private EntityManagerInterface&MockObject $entityManager;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(ShipmentTrackingRepositoryInterface::class);
        $this->processor = $this->createMock(ShipmentTrackingProcessorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->commandTester = new CommandTester(new SendShipmentTrackingCommand($this->repository, $this->processor, $this->entityManager));
    }

    #[Test]
    public function it_processes_every_pending_or_failed_tracking_in_batches_using_the_last_id_as_a_cursor(): void
    {
        $first = $this->tracking(1, ShipmentTrackingInterface::STATE_SYNCED);
        $second = $this->tracking(2, ShipmentTrackingInterface::STATE_SYNCED);
        $third = $this->tracking(3, ShipmentTrackingInterface::STATE_SYNCED);

        $this->repository
            ->expects(self::exactly(3))
            ->method('findPendingOrFailed')
            ->willReturnCallback(fn (int $limit, ?int $afterId): array => match ([$limit, $afterId]) {
                [2, null] => [$first, $second],
                [2, 2] => [$third],
                [2, 3] => [],
                default => self::fail(sprintf('Unexpected findPendingOrFailed(%d, %s) call.', $limit, var_export($afterId, true))),
            });
        $this->processor->expects(self::exactly(3))->method('process');
        $this->entityManager->expects(self::exactly(2))->method('clear');

        self::assertSame(Command::SUCCESS, $this->commandTester->execute(['--batch-size' => '2']));
        self::assertStringContainsString('Processed 3 shipment tracking record(s): 3 synced, 0 still failing.', $this->commandTester->getDisplay());
    }

    #[Test]
    public function it_does_not_reprocess_permanently_failed_trackings_within_one_run(): void
    {
        $failed = $this->tracking(1, ShipmentTrackingInterface::STATE_FAILED);
        $pending = $this->tracking(2, ShipmentTrackingInterface::STATE_SYNCED);

        $this->repository
            ->method('findPendingOrFailed')
            ->willReturnCallback(fn (int $limit, ?int $afterId): array => match ($afterId) {
                null => [$failed],
                1 => [$pending],
                2 => [],
                default => self::fail(sprintf('Unexpected findPendingOrFailed(%d, %s) call.', $limit, var_export($afterId, true))),
            });
        $this->processor->expects(self::exactly(2))->method('process');

        self::assertSame(Command::FAILURE, $this->commandTester->execute(['--batch-size' => '1']));
        self::assertStringContainsString('1 synced, 1 still failing', $this->commandTester->getDisplay());
    }

    #[Test]
    public function it_uses_a_default_batch_size_of_100(): void
    {
        $this->repository->expects(self::once())->method('findPendingOrFailed')->with(100, null)->willReturn([]);

        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        self::assertStringContainsString('No pending or failed shipment tracking to send.', $this->commandTester->getDisplay());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidBatchSizes(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'not a number' => ['abc'];
        yield 'fraction' => ['1.5'];
    }

    #[Test]
    #[DataProvider('invalidBatchSizes')]
    public function it_rejects_an_invalid_batch_size(string $batchSize): void
    {
        $this->repository->expects(self::never())->method('findPendingOrFailed');

        self::assertSame(Command::INVALID, $this->commandTester->execute(['--batch-size' => $batchSize]));
        self::assertStringContainsString('positive integer', $this->commandTester->getDisplay());
    }

    #[Test]
    public function it_fails_when_a_tracking_is_still_failing(): void
    {
        $tracking = $this->tracking(1, ShipmentTrackingInterface::STATE_FAILED);
        $tracking->method('getLastError')->willReturn('No carrier selected for the shipment.');

        $this->repository->method('findPendingOrFailed')->willReturnOnConsecutiveCalls([$tracking], []);

        self::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        self::assertStringContainsString('No carrier selected for the shipment.', $this->commandTester->getDisplay());
    }

    #[Test]
    public function it_keeps_processing_when_the_processor_throws(): void
    {
        $failing = $this->tracking(1, ShipmentTrackingInterface::STATE_FAILED);
        $synced = $this->tracking(2, ShipmentTrackingInterface::STATE_SYNCED);

        $this->repository->method('findPendingOrFailed')->willReturnOnConsecutiveCalls([$failing, $synced], []);
        $this->processor
            ->expects(self::exactly(2))
            ->method('process')
            ->willReturnCallback(function (ShipmentInterface $shipment) use ($failing): void {
                if ($failing->getShipment() === $shipment) {
                    throw new \RuntimeException('PayPal is down');
                }
            });

        self::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        self::assertStringContainsString('PayPal is down', $this->commandTester->getDisplay());
        self::assertStringContainsString('1 synced, 1 still failing', $this->commandTester->getDisplay());
    }

    private function tracking(int $id, string $state): MockObject&ShipmentTrackingInterface
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getId')->willReturn($id);

        $tracking = $this->createMock(ShipmentTrackingInterface::class);
        $tracking->method('getId')->willReturn($id);
        $tracking->method('getShipment')->willReturn($shipment);
        $tracking->method('getState')->willReturn($state);

        return $tracking;
    }
}
