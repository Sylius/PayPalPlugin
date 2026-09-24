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

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrarInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sylius-paypal:register-webhook-event-types',
    description: 'Subscribes every registered PayPal webhook to the events this plugin handles',
)]
final class RegisterWebhookEventTypesCommand extends Command
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly SellerWebhookEventTypesRegistrarInterface $registrar,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failed = false;

        foreach ($this->paymentMethodRepository->findAll() as $paymentMethod) {
            if (!$this->isPayPalPaymentMethod($paymentMethod)) {
                continue;
            }

            try {
                $this->registrar->register($paymentMethod);
                $io->success(sprintf('Updated the webhook of "%s".', (string) $paymentMethod->getCode()));
            } catch (\Throwable $exception) {
                $failed = true;
                $io->error(sprintf(
                    'Could not update the webhook of "%s": %s',
                    (string) $paymentMethod->getCode(),
                    $exception->getMessage(),
                ));
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function isPayPalPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return
            $gatewayConfig instanceof GatewayConfigInterface &&
            $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ;
    }
}
