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

    public function complete(PaymentInterface $payment): void
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
        $this->paymentManager->flush();
    }
}
