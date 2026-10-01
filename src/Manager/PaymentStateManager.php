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

namespace Sylius\PayPalPlugin\Manager;

use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Checker\PayerActionChecker;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;

final readonly class PaymentStateManager implements PaymentStateManagerInterface
{
    private PayerActionCheckerInterface $payerActionChecker;

    public function __construct(
        private StateMachineInterface $stateMachineFactory,
        private ObjectManager $paymentManager,
        private PaymentCompleteProcessorInterface $paypalPaymentCompleteProcessor,
        ?PayerActionCheckerInterface $payerActionChecker = null,
    ) {
        $this->payerActionChecker = $payerActionChecker ?? new PayerActionChecker();
    }

    public function create(PaymentInterface $payment): void
    {
        $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_CREATE);
    }

    public function process(PaymentInterface $payment): void
    {
        $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_PROCESS);
    }

    public function complete(PaymentInterface $payment): void
    {
        // TODO - move target state resolving to the separate service
        $this->paypalPaymentCompleteProcessor->completePayment($payment);

        $status = (string) $payment->getDetails()['status'];
        if ($status === StatusAction::STATUS_COMPLETED) {
            $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_COMPLETE);

            return;
        }

        if (
            $status === StatusAction::STATUS_PROCESSING &&
            $payment->getState() !== PaymentInterface::STATE_PROCESSING
        ) {
            $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_PROCESS);
        }
    }

    public function cancel(PaymentInterface $payment): void
    {
        if ($this->payerActionChecker->isAwaitingPayerAction($payment)) {
            return;
        }

        $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_CANCEL);
    }

    private function applyTransitionAndSave(PaymentInterface $payment, string $transition): void
    {
        $this->stateMachineFactory->apply($payment, PaymentTransitions::GRAPH, $transition);
        $this->paymentManager->flush();
    }
}
