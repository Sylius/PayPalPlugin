<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Manager;

use Doctrine\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;

final class PaymentStateManagerSpec extends ObjectBehavior
{
    function let(FactoryInterface $stateMachineFactory, ObjectManager $paymentManager): void
    {
        $this->beConstructedWith($stateMachineFactory, $paymentManager);
    }

    function it_implements_payment_state_manager_interface(): void
    {
        $this->shouldImplement(PaymentStateManagerInterface::class);
    }

    function it_creates_payment(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentInterface $payment,
        StateMachineInterface $stateMachine
    ): void {
        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->apply(PaymentTransitions::TRANSITION_CREATE)->shouldBeCalled();
        $paymentManager->flush()->shouldBeCalled();

        $this->create($payment);
    }

    function it_completes_payment(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentInterface $payment,
        StateMachineInterface $stateMachine
    ): void {
        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE)->shouldBeCalled();
        $paymentManager->flush()->shouldBeCalled();

        $this->complete($payment);
    }

    function it_processes_payment(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentInterface $payment,
        StateMachineInterface $stateMachine
    ): void {
        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS)->shouldBeCalled();
        $paymentManager->flush()->shouldBeCalled();

        $this->process($payment);
    }

    function it_cancels_payment(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentInterface $payment,
        StateMachineInterface $stateMachine
    ): void {
        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->apply(PaymentTransitions::TRANSITION_CANCEL)->shouldBeCalled();
        $paymentManager->flush()->shouldBeCalled();

        $this->cancel($payment);
    }
}
