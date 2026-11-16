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

namespace Tests\Sylius\PayPalPlugin\Unit\Dispatcher;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Command\RefundPayPalPaymentRequest;
use Sylius\PayPalPlugin\Dispatcher\RefundPayPalPaymentRequestDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RefundPayPalPaymentRequestDispatcherTest extends TestCase
{
    /** @var PaymentRequestFactoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestFactoryInterface&MockObject $paymentRequestFactory;

    /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    private StateMachineInterface&MockObject $stateMachine;

    private MessageBusInterface&MockObject $commandBus;

    private PaymentMethod $paymentMethod;

    private Payment $payment;

    private RefundPayPalPaymentRequestDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->paymentRequestFactory = $this->createMock(PaymentRequestFactoryInterface::class);
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->paymentMethod = new PaymentMethod();
        $this->payment = new Payment();
        $this->payment->setMethod($this->paymentMethod);

        $this->dispatcher = new RefundPayPalPaymentRequestDispatcher(
            $this->paymentRequestFactory,
            $this->paymentRequestRepository,
            $this->stateMachine,
            $this->commandBus,
        );
    }

    public function test_it_creates_a_processing_refund_request_and_dispatches_its_refund(): void
    {
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::ACTION_REFUND, 'REFUND_REQUEST_HASH');
        $this->paymentRequestRepository
            ->method('findByPaymentIdAndStates')
            ->with($this->payment->getId(), [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING])
            ->willReturn([$this->paymentRequest(PaymentRequestInterface::ACTION_CAPTURE, 'CAPTURE_REQUEST_HASH')])
        ;

        $this->paymentRequestFactory->expects(self::once())->method('create')->with($this->payment, $this->paymentMethod)->willReturn($paymentRequest);
        $paymentRequest->expects(self::once())->method('setAction')->with(PaymentRequestInterface::ACTION_REFUND);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        $this->paymentRequestRepository->expects(self::once())->method('add')->with($paymentRequest);
        $this->expectRefundDispatchedFor('REFUND_REQUEST_HASH');

        self::assertSame($paymentRequest, $this->dispatcher->dispatch($this->payment));
    }

    public function test_it_dispatches_the_refund_of_the_open_refund_request_again(): void
    {
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::ACTION_REFUND, 'OPEN_REFUND_REQUEST_HASH');
        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([$paymentRequest]);

        $this->paymentRequestFactory->expects(self::never())->method('create');
        $this->stateMachine->expects(self::never())->method('apply');
        $this->paymentRequestRepository->expects(self::never())->method('add');
        $this->expectRefundDispatchedFor('OPEN_REFUND_REQUEST_HASH');

        self::assertSame($paymentRequest, $this->dispatcher->dispatch($this->payment));
    }

    private function paymentRequest(string $action, string $hash): PaymentRequestInterface&MockObject
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn($action);
        $paymentRequest->method('getId')->willReturn($hash);

        return $paymentRequest;
    }

    private function expectRefundDispatchedFor(string $hash): void
    {
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (RefundPayPalPaymentRequest $command): bool => $hash === $command->getHash()))
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command))
        ;
    }
}
