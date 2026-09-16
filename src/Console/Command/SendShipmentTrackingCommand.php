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

use Sylius\PayPalPlugin\Entity\ShipmentTrackingInterface;
use Sylius\PayPalPlugin\Processor\ShipmentTrackingProcessorInterface;
use Sylius\PayPalPlugin\Repository\ShipmentTrackingRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sylius-paypal:send-shipment-tracking',
    description: 'Sends (or retries) PayPal tracking for shipments whose sync is pending or failed',
)]
final class SendShipmentTrackingCommand extends Command
{
    public function __construct(
        private readonly ShipmentTrackingRepositoryInterface $shipmentTrackingRepository,
        private readonly ShipmentTrackingProcessorInterface $shipmentTrackingProcessor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $trackings = $this->shipmentTrackingRepository->findPendingOrFailed();
        if ([] === $trackings) {
            $io->success('No pending or failed shipment tracking to send.');

            return Command::SUCCESS;
        }

        $synced = 0;
        $failed = 0;
        foreach ($trackings as $tracking) {
            try {
                $this->shipmentTrackingProcessor->process($tracking->getShipment());
            } catch (\Throwable $exception) {
                ++$failed;
                $io->warning(sprintf(
                    'Shipment #%s: %s',
                    (string) $tracking->getShipment()->getId(),
                    $exception->getMessage(),
                ));

                continue;
            }

            if (ShipmentTrackingInterface::STATE_SYNCED === $tracking->getState()) {
                ++$synced;
            } else {
                ++$failed;
                $io->warning(sprintf(
                    'Shipment #%s: %s',
                    $tracking->getShipment()->getId(),
                    $tracking->getLastError(),
                ));
            }
        }

        $io->success(sprintf('Processed %d shipment tracking record(s): %d synced, %d still failing.', count($trackings), $synced, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
