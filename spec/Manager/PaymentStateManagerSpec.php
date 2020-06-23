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

    function it_changes_state_of_payment(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentInterface $payment,
        StateMachineInterface $stateMachine
    ): void {
        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->getTransitionToState('completed')->willReturn('complete');

        $stateMachine->apply('complete')->shouldBeCalled();
        $paymentManager->flush()->shouldBeCalled();

        $this->changeState($payment, 'COMPLETED');
    }

    function it_throws_an_exception_if_transition_is_not_possible(
        FactoryInterface $stateMachineFactory,
        PaymentInterface $payment,
        StateMachineInterface $stateMachine
    ): void {
        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->getTransitionToState('invalid')->willReturn(null);

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('changeState', [$payment, 'invalid'])
        ;
    }
}
