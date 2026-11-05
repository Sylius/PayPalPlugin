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

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\CommandHandler\StatusPaymentRequestHandler;
use Sylius\PayPalPlugin\Dispatcher\StatusPaymentRequestDispatcherInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentBatchQueryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sylius-paypal:settle-payments',
    description: 'Settles processing and authorized PayPal payments with the state of their PayPal orders',
    aliases: ['sylius-paypal:complete-payments'],
)]
final class CompletePaidPaymentsCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 100;

    private const SETTLEABLE_STATES = [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED];

    public function __construct(
        private readonly PaypalPaymentBatchQueryInterface $paypalPaymentBatchQuery,
        private readonly StatusPaymentRequestDispatcherInterface $statusPaymentRequestDispatcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'How many payments are loaded at once', self::DEFAULT_BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = false;

        foreach ($this->findSettleablePayments(max(1, (int) $input->getOption('batch-size'))) as $payment) {
            $reason = $this->settle($payment);
            if (null === $reason) {
                continue;
            }

            $failed = true;
            $output->writeln(sprintf(
                '<error>Payment #%s of order #%s could not be settled: %s</error>',
                (string) $payment->getId(),
                (string) $payment->getOrder()?->getNumber(),
                $reason,
            ));
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /** @return iterable<PaymentInterface> */
    private function findSettleablePayments(int $batchSize): iterable
    {
        $lastId = null;

        while ([] !== $payments = $this->paypalPaymentBatchQuery->findInStates(self::SETTLEABLE_STATES, $batchSize, $lastId)) {
            foreach ($payments as $payment) {
                /** @var int $lastId */
                $lastId = $payment->getId();

                yield $payment;
            }

            $this->entityManager->clear();
        }
    }

    private function settle(PaymentInterface $payment): ?string
    {
        try {
            $paymentRequest = $this->statusPaymentRequestDispatcher->dispatch($payment);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }

        if (PaymentRequestInterface::STATE_FAILED !== $paymentRequest->getState()) {
            return null;
        }

        $responseData = $paymentRequest->getResponseData();
        $reason = $responseData[StatusPaymentRequestHandler::RESPONSE_REASON] ?? null;

        return is_string($reason) ? $reason : 'the status Payment Request failed.';
    }
}
