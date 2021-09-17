<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Manager;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;

final class PaymentStateManager implements PaymentStateManagerInterface
{
    private FactoryInterface $stateMachineFactory;

    private ObjectManager $paymentManager;

    private PaymentCompleteProcessorInterface $paypalPaymentCompleteProcessor;

    public function __construct(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentCompleteProcessorInterface $paypalPaymentCompleteProcessor
    ) {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentManager = $paymentManager;
        $this->paypalPaymentCompleteProcessor = $paypalPaymentCompleteProcessor;
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
        $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_CANCEL);
    }

    private function applyTransitionAndSave(PaymentInterface $payment, string $transition): void
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

        $stateMachine->apply($transition);
        $this->paymentManager->flush();
    }
}
