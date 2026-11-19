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

namespace Sylius\PayPalPlugin\Dispatcher;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Command\ChargeVaultedPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\ChargeVaultedPaymentRequestHandler;
use Sylius\PayPalPlugin\CommandProvider\ChargeVaultedPaymentRequestCommandProvider;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ChargeVaultedPaymentRequestDispatcher implements ChargeVaultedPaymentRequestDispatcherInterface
{
    /**
     * @param PaymentRequestFactoryInterface<PaymentRequestInterface> $paymentRequestFactory
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     */
    public function __construct(
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private EntityManagerInterface $paymentManager,
        private StateMachineInterface $stateMachine,
        private MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(PaymentInterface $payment, string $vaultId): PaymentRequestInterface
    {
        if (PaymentInterface::STATE_NEW !== $payment->getState()) {
            throw new \LogicException(sprintf('Only a new payment can be charged with a vaulted PayPal wallet, payment "%s" is %s.', $payment->getId(), $payment->getState()));
        }

        $paymentRequest = $this->paymentManager->wrapInTransaction(function () use ($payment, $vaultId): PaymentRequestInterface {
            $this->paymentManager->lock($payment, LockMode::PESSIMISTIC_WRITE);

            return $this->openPaymentRequest($payment, $vaultId) ?? $this->createPaymentRequest($payment, $vaultId);
        });

        $this->commandBus->dispatch(new ChargeVaultedPaymentRequest($paymentRequest->getId()));

        return $paymentRequest;
    }

    private function openPaymentRequest(PaymentInterface $payment, string $vaultId): ?PaymentRequestInterface
    {
        $paymentRequests = $this->paymentRequestRepository->findByPaymentIdAndStates(
            $payment->getId(),
            [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING],
        );

        foreach ($paymentRequests as $paymentRequest) {
            if (ChargeVaultedPaymentRequestCommandProvider::ACTION !== $paymentRequest->getAction()) {
                continue;
            }

            if (PaymentRequestInterface::STATE_NEW === $paymentRequest->getState()) {
                $paymentRequest->setPayload([ChargeVaultedPaymentRequestHandler::PAYLOAD_VAULT_ID => $vaultId]);
                $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
            }

            return $paymentRequest;
        }

        return null;
    }

    private function createPaymentRequest(PaymentInterface $payment, string $vaultId): PaymentRequestInterface
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $paymentRequest = $this->paymentRequestFactory->create($payment, $paymentMethod);
        $paymentRequest->setAction(ChargeVaultedPaymentRequestCommandProvider::ACTION);
        $paymentRequest->setPayload([ChargeVaultedPaymentRequestHandler::PAYLOAD_VAULT_ID => $vaultId]);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        $this->paymentRequestRepository->add($paymentRequest);

        return $paymentRequest;
    }
}
