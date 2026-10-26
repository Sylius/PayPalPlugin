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
use Doctrine\Persistence\ObjectManager;
use Psr\Log\NullLogger;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;
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

    /**
     * @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository
     *
     * @deprecated the $paymentManager, $authorizeClientApi, $orderDetailsApi and $stateMachine arguments are
     *             deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0.
     *             Pass a $paymentSettlementProcessor instead.
     */
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly ?ObjectManager $paymentManager = null,
        private readonly ?CacheAuthorizeClientApiInterface $authorizeClientApi = null,
        private readonly ?OrderDetailsApiInterface $orderDetailsApi = null,
        private readonly ?StateMachineInterface $stateMachine = null,
        private readonly ?PaymentSettlementProcessorInterface $paymentSettlementProcessor = null,
        private readonly ?PaypalPaymentBatchQueryInterface $paypalPaymentBatchQuery = null,
        private readonly ?EntityManagerInterface $entityManager = null,
    ) {
        parent::__construct();

        foreach ([
            ObjectManager::class => $this->paymentManager,
            CacheAuthorizeClientApiInterface::class => $this->authorizeClientApi,
            OrderDetailsApiInterface::class => $this->orderDetailsApi,
            StateMachineInterface::class => $this->stateMachine,
        ] as $class => $argument) {
            if (null !== $argument) {
                trigger_deprecation(
                    'sylius/paypal-plugin',
                    '2.2',
                    'Passing an instance of "%s" to "%s" constructor is deprecated and will be prohibited in 3.0.',
                    $class,
                    self::class,
                );
            }
        }

        if (null === $this->paymentSettlementProcessor) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing an instance of "%s" to "%s" constructor is deprecated and will be required in 3.0.',
                PaymentSettlementProcessorInterface::class,
                self::class,
            );
        }
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'How many payments are loaded at once', self::DEFAULT_BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paymentSettlementProcessor = $this->paymentSettlementProcessor ?? $this->buildPaymentSettlementProcessor();

        if (null === $paymentSettlementProcessor) {
            $output->writeln(sprintf(
                '<error>No %s was given, so no payment can be settled.</error>',
                PaymentSettlementProcessorInterface::class,
            ));

            return Command::FAILURE;
        }

        $failed = false;

        foreach ($this->findSettleablePayments(max(1, (int) $input->getOption('batch-size'))) as $payment) {
            if (!$this->isPayPalPayment($payment)) {
                continue;
            }

            try {
                $paymentSettlementProcessor->settle($payment);
            } catch (\Exception $exception) {
                $failed = true;
                $output->writeln(sprintf(
                    '<error>Payment #%s of order #%s could not be settled: %s</error>',
                    (string) $payment->getId(),
                    (string) $payment->getOrder()?->getNumber(),
                    $exception->getMessage(),
                ));
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /** @return iterable<PaymentInterface> */
    private function findSettleablePayments(int $batchSize): iterable
    {
        if (null === $this->paypalPaymentBatchQuery) {
            yield from $this->paymentRepository->findBy(['state' => self::SETTLEABLE_STATES]);

            return;
        }

        $lastId = null;

        while ([] !== $payments = $this->paypalPaymentBatchQuery->findInStates(self::SETTLEABLE_STATES, $batchSize, $lastId)) {
            foreach ($payments as $payment) {
                /** @var int $lastId */
                $lastId = $payment->getId();

                yield $payment;
            }

            $this->entityManager?->clear();
        }
    }

    private function buildPaymentSettlementProcessor(): ?PaymentSettlementProcessorInterface
    {
        if (
            null === $this->authorizeClientApi ||
            null === $this->orderDetailsApi ||
            null === $this->stateMachine ||
            null === $this->paymentManager
        ) {
            return null;
        }

        return new PayPalPaymentSettlementProcessor(
            $this->orderDetailsApi,
            $this->stateMachine,
            $this->paymentManager,
            new NullLogger(),
        );
    }

    private function isPayPalPayment(PaymentInterface $payment): bool
    {
        $paymentMethod = $payment->getMethod();
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            return false;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return
            $gatewayConfig instanceof GatewayConfigInterface &&
            $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ;
    }
}
