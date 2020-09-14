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
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;

final class PaymentStateManagerSpec extends ObjectBehavior
{
    function let(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentCompleteProcessorInterface $paymentCompleteProcessor
    ): void {
        $this->beConstructedWith($stateMachineFactory, $paymentManager, $paymentCompleteProcessor);
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

    function it_completes_payment_if_its_completed_in_paypal(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentCompleteProcessorInterface $paymentCompleteProcessor,
        PaymentInterface $payment,
        StateMachineInterface $stateMachine
    ): void {
        $paymentCompleteProcessor->completePayment($payment);
        $payment->getDetails()->willReturn(['status' => StatusAction::STATUS_COMPLETED]);

        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE)->shouldBeCalled();
        $paymentManager->flush()->shouldBeCalled();

        $this->complete($payment);
    }

    function it_processes_payment_if_its_processing_in_paypal_and_not_processing_in_sylius_yet(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentCompleteProcessorInterface $paymentCompleteProcessor,
        PaymentInterface $payment,
        StateMachineInterface $stateMachine
    ): void {
        $paymentCompleteProcessor->completePayment($payment);
        $payment->getDetails()->willReturn(['status' => StatusAction::STATUS_PROCESSING]);
        $payment->getState()->willReturn(PaymentInterface::STATE_NEW);

        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->willReturn($stateMachine);
        $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS)->shouldBeCalled();
        $paymentManager->flush()->shouldBeCalled();

        $this->complete($payment);
    }

    function it_does_nothing_if_payment_is_processing_in_paypal_but_already_processing_in_sylius(
        FactoryInterface $stateMachineFactory,
        PaymentCompleteProcessorInterface $paymentCompleteProcessor,
        PaymentInterface $payment
    ): void {
        $paymentCompleteProcessor->completePayment($payment);
        $payment->getDetails()->willReturn(['status' => StatusAction::STATUS_PROCESSING]);
        $payment->getState()->willReturn(PaymentInterface::STATE_PROCESSING);

        $stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->shouldNotBeCalled();

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
