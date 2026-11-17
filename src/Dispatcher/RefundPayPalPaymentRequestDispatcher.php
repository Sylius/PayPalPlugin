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

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Command\RefundPayPalPaymentRequest;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RefundPayPalPaymentRequestDispatcher implements RefundPayPalPaymentRequestDispatcherInterface
{
    /**
     * @param PaymentRequestFactoryInterface<PaymentRequestInterface> $paymentRequestFactory
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     */
    public function __construct(
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private StateMachineInterface $stateMachine,
        private MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(PaymentInterface $payment): PaymentRequestInterface
    {
        $paymentRequest = $this->openPaymentRequest($payment) ?? $this->createPaymentRequest($payment);

        $this->commandBus->dispatch(new RefundPayPalPaymentRequest($paymentRequest->getId()));

        return $paymentRequest;
    }

    private function openPaymentRequest(PaymentInterface $payment): ?PaymentRequestInterface
    {
        $paymentRequests = $this->paymentRequestRepository->findByPaymentIdAndStates(
            $payment->getId(),
            [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING],
        );

        foreach ($paymentRequests as $paymentRequest) {
            if (PaymentRequestInterface::ACTION_REFUND === $paymentRequest->getAction()) {
                return $paymentRequest;
            }
        }

        return null;
    }

    private function createPaymentRequest(PaymentInterface $payment): PaymentRequestInterface
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $paymentRequest = $this->paymentRequestFactory->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_REFUND);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);

        $this->paymentRequestRepository->add($paymentRequest);

        return $paymentRequest;
    }
}
