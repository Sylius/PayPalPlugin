<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Manager;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;
use Webmozart\Assert\Assert;

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

    public function changeState(PaymentInterface $payment, string $targetState): void
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $transition = $stateMachine->getTransitionToState(strtolower($targetState));

        Assert::notNull($transition);

        $stateMachine->apply($transition);
        $this->paymentManager->flush();
    }
}
