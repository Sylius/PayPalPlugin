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

use Doctrine\Persistence\ObjectManager;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sylius-paypal:refresh-vaulting-capability',
    description: 'Reads again from PayPal whether every PayPal payment method can vault the PayPal wallet',
)]
final class RefreshVaultingCapabilityCommand extends Command
{
    public function __construct(
        private readonly PayPalPaymentMethodQueryInterface $payPalPaymentMethodQuery,
        private readonly VaultingCapabilityRefresherInterface $refresher,
        private readonly ObjectManager $paymentMethodManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failed = false;

        foreach ($this->payPalPaymentMethodQuery->findAll() as $paymentMethod) {
            try {
                $io->success(sprintf(
                    'Vaulting is %s for "%s".',
                    $this->refresher->refresh($paymentMethod) ? 'enabled' : 'not enabled',
                    (string) $paymentMethod->getCode(),
                ));
            } catch (\Throwable $exception) {
                $failed = true;
                $io->error(sprintf(
                    'Could not refresh the vaulting capability of "%s": %s',
                    (string) $paymentMethod->getCode(),
                    $exception->getMessage(),
                ));
            }
        }

        $this->paymentMethodManager->flush();

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
