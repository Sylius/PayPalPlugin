<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Manager;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;

final class PaymentStateManager implements PaymentStateManagerInterface
{
    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var ObjectManager */
    private $paymentManager;

    public function __construct(FactoryInterface $stateMachineFactory, ObjectManager $paymentManager)
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentManager = $paymentManager;
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
        $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_COMPLETE);
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
