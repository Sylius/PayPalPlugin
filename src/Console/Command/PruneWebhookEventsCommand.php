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

namespace Sylius\PayPalPlugin\Console\Command;

use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sylius-paypal:prune-webhook-events',
    description: 'Removes PayPal webhook events older than the retention period',
)]
final class PruneWebhookEventsCommand extends Command
{
    public function __construct(
        private readonly PayPalWebhookEventRepositoryInterface $webhookEventRepository,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Remove events received more than this many days ago', (string) $this->retentionDays);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = $input->getOption('days');
        if (false === filter_var($days, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            $io->error('The --days option must be a positive integer.');

            return Command::INVALID;
        }

        $removed = $this->webhookEventRepository->removeCreatedBefore(new \DateTime(sprintf('-%d days', (int) $days)));

        $io->success(sprintf('Removed %d PayPal webhook event(s) older than %d day(s).', $removed, (int) $days));

        return Command::SUCCESS;
    }
}
