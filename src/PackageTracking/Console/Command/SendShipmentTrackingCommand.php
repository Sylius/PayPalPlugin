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

namespace Sylius\PayPalPlugin\PackageTracking\Console\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\PackageTracking\Processor\ShipmentTrackingProcessorInterface;
use Sylius\PayPalPlugin\PackageTracking\Repository\ShipmentTrackingRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sylius-paypal:send-shipment-tracking',
    description: 'Sends (or retries) PayPal tracking for shipments whose sync is pending or failed',
)]
final class SendShipmentTrackingCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private readonly ShipmentTrackingRepositoryInterface $shipmentTrackingRepository,
        private readonly ShipmentTrackingProcessorInterface $shipmentTrackingProcessor,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of shipment tracking records loaded per batch', (string) self::DEFAULT_BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $batchSize = $input->getOption('batch-size');
        if (1 > (int) $batchSize || false === filter_var($batchSize, \FILTER_VALIDATE_INT)) {
            $io->error('The --batch-size option must be a positive integer.');

            return Command::INVALID;
        }

        $batchSize = (int) $batchSize;
        $processed = 0;
        $synced = 0;
        $failed = 0;
        $lastId = null;

        while ([] !== $trackings = $this->shipmentTrackingRepository->findPendingOrFailed($batchSize, $lastId)) {
            foreach ($trackings as $tracking) {
                ++$processed;
                $lastId = $tracking->getId();

                if ($this->send($tracking, $io)) {
                    ++$synced;
                } else {
                    ++$failed;
                }
            }

            $this->entityManager->clear();
        }

        if (0 === $processed) {
            $io->success('No pending or failed shipment tracking to send.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('Processed %d shipment tracking record(s): %d synced, %d still failing.', $processed, $synced, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function send(ShipmentTrackingInterface $tracking, SymfonyStyle $io): bool
    {
        try {
            $this->shipmentTrackingProcessor->process($tracking->getShipment());
        } catch (\Throwable $exception) {
            $io->warning(sprintf(
                'Shipment #%s: %s',
                (string) $tracking->getShipment()->getId(),
                $exception->getMessage(),
            ));

            return false;
        }

        if (ShipmentTrackingInterface::STATE_SYNCED === $tracking->getState()) {
            return true;
        }

        $io->warning(sprintf(
            'Shipment #%s: %s',
            (string) $tracking->getShipment()->getId(),
            (string) $tracking->getLastError(),
        ));

        return false;
    }
}
