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

namespace Tests\Sylius\PayPalPlugin\Unit\Console\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Console\Command\PruneWebhookEventsCommand;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneWebhookEventsCommandTest extends TestCase
{
    private PayPalWebhookEventRepositoryInterface&MockObject $webhookEventRepository;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookEventRepository = $this->createMock(PayPalWebhookEventRepositoryInterface::class);

        $this->commandTester = new CommandTester(new PruneWebhookEventsCommand($this->webhookEventRepository, 30));
    }

    public function test_it_removes_events_older_than_the_configured_retention(): void
    {
        $this->webhookEventRepository
            ->expects(self::once())
            ->method('removeCreatedBefore')
            ->with(self::callback(static fn (\DateTimeInterface $date): bool => abs($date->getTimestamp() - strtotime('-30 days')) < 5))
            ->willReturn(3)
        ;

        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        self::assertStringContainsString('Removed 3 PayPal webhook event(s) older than 30 day(s).', $this->commandTester->getDisplay());
    }

    public function test_it_removes_events_older_than_the_given_number_of_days(): void
    {
        $this->webhookEventRepository
            ->expects(self::once())
            ->method('removeCreatedBefore')
            ->with(self::callback(static fn (\DateTimeInterface $date): bool => abs($date->getTimestamp() - strtotime('-7 days')) < 5))
            ->willReturn(0)
        ;

        self::assertSame(Command::SUCCESS, $this->commandTester->execute(['--days' => '7']));
    }

    public function test_it_rejects_a_number_of_days_that_is_not_a_positive_integer(): void
    {
        $this->webhookEventRepository->expects(self::never())->method('removeCreatedBefore');

        self::assertSame(Command::INVALID, $this->commandTester->execute(['--days' => '0']));
        self::assertSame(Command::INVALID, $this->commandTester->execute(['--days' => 'week']));
    }
}
