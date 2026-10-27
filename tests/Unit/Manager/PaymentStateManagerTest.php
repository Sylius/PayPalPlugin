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

namespace Tests\Sylius\PayPalPlugin\Unit\Manager;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Manager\PaymentStateManager;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;

final class PaymentStateManagerTest extends TestCase
{
    private StateMachineInterface&MockObject $stateMachine;

    private ObjectManager&MockObject $paymentManager;

    private PaymentCompleteProcessorInterface&MockObject $paymentCompleteProcessor;

    private PaymentStateManager $paymentStateManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->paymentManager = $this->createMock(ObjectManager::class);
        $this->paymentCompleteProcessor = $this->createMock(PaymentCompleteProcessorInterface::class);

        $this->paymentStateManager = new PaymentStateManager(
            $this->stateMachine,
            $this->paymentManager,
            $this->paymentCompleteProcessor,
        );
    }

    #[Test]
    public function it_implements_payment_state_manager_interface(): void
    {
        self::assertInstanceOf(PaymentStateManagerInterface::class, $this->paymentStateManager);
    }

    #[Test]
    public function it_creates_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE);

        $this->paymentManager
            ->expects(self::once())
            ->method('flush');

        $this->paymentStateManager->create($payment);
    }

    #[Test]
    public function it_completes_payment_if_its_completed_in_paypal(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->paymentCompleteProcessor
            ->expects(self::once())
            ->method('completePayment')
            ->with($payment);

        $payment
            ->expects(self::once())
            ->method('getDetails')
            ->willReturn(['status' => StatusAction::STATUS_COMPLETED]);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        $this->paymentManager
            ->expects(self::once())
            ->method('flush');

        $this->paymentStateManager->complete($payment);
    }

    #[Test]
    public function it_processes_payment_if_its_processing_in_paypal_and_not_processing_in_sylius_yet(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->paymentCompleteProcessor
            ->expects(self::once())
            ->method('completePayment')
            ->with($payment);

        $payment
            ->expects(self::once())
            ->method('getDetails')
            ->willReturn(['status' => StatusAction::STATUS_PROCESSING]);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS);

        $this->paymentManager
            ->expects(self::once())
            ->method('flush');

        $this->paymentStateManager->complete($payment);
    }

    #[Test]
    public function it_does_nothing_if_payment_is_processing_in_paypal_but_already_processing_in_sylius(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->paymentCompleteProcessor
            ->expects(self::once())
            ->method('completePayment')
            ->with($payment);

        $payment
            ->expects(self::once())
            ->method('getDetails')
            ->willReturn(['status' => StatusAction::STATUS_PROCESSING]);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_PROCESSING);

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->paymentStateManager->complete($payment);
    }

    #[Test]
    public function it_processes_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS);

        $this->paymentManager
            ->expects(self::once())
            ->method('flush');

        $this->paymentStateManager->process($payment);
    }

    #[Test]
    public function it_cancels_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

        $this->paymentManager
            ->expects(self::once())
            ->method('flush');

        $this->paymentStateManager->cancel($payment);
    }

    public function test_it_never_cancels_a_payment_the_payer_is_finishing_off_site(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->method('getDetails')->willReturn([
            'payment_source' => 'trustly',
            'payer_action_url' => 'https://www.paypal.com/payment/trustly?token=X',
        ]);

        $this->stateMachine->expects(self::never())->method('apply');
        $this->paymentManager->expects(self::never())->method('flush');

        $this->paymentStateManager->cancel($payment);
    }
}
