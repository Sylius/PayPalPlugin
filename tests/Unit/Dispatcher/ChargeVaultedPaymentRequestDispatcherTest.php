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

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Command\ChargeVaultedPaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\ChargeVaultedPaymentRequestCommandProvider;
use Sylius\PayPalPlugin\Dispatcher\ChargeVaultedPaymentRequestDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ChargeVaultedPaymentRequestDispatcherTest extends TestCase
{
    /** @var PaymentRequestFactoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestFactoryInterface&MockObject $paymentRequestFactory;

    /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    private EntityManagerInterface&MockObject $paymentManager;

    private StateMachineInterface&MockObject $stateMachine;

    private MessageBusInterface&MockObject $commandBus;

    private PaymentMethod $paymentMethod;

    private Payment $payment;

    /** @var list<string> */
    private array $calls = [];

    private ChargeVaultedPaymentRequestDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->paymentRequestFactory = $this->createMock(PaymentRequestFactoryInterface::class);
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentManager->method('wrapInTransaction')->willReturnCallback(function (callable $function): mixed {
            $this->calls[] = 'begin';
            $result = $function();
            $this->calls[] = 'commit';

            return $result;
        });
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->paymentMethod = new PaymentMethod();
        $this->payment = new Payment();
        $this->payment->setMethod($this->paymentMethod);

        $this->dispatcher = new ChargeVaultedPaymentRequestDispatcher(
            $this->paymentRequestFactory,
            $this->paymentRequestRepository,
            $this->paymentManager,
            $this->stateMachine,
            $this->commandBus,
        );
    }

    public function test_it_creates_a_processing_charge_request_and_dispatches_the_charge(): void
    {
        $paymentRequest = $this->paymentRequest(ChargeVaultedPaymentRequestCommandProvider::ACTION, 'CHARGE_REQUEST_HASH');
        $this->paymentRequestRepository
            ->method('findByPaymentIdAndStates')
            ->with($this->payment->getId(), [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING])
            ->willReturn([$this->paymentRequest(PaymentRequestInterface::ACTION_CAPTURE, 'CAPTURE_REQUEST_HASH')])
        ;

        $this->paymentRequestFactory->expects(self::once())->method('create')->with($this->payment, $this->paymentMethod)->willReturn($paymentRequest);
        $paymentRequest->expects(self::once())->method('setAction')->with(ChargeVaultedPaymentRequestCommandProvider::ACTION);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        $this->paymentRequestRepository->expects(self::once())->method('add')->with($paymentRequest);
        $this->expectChargeDispatchedFor('CHARGE_REQUEST_HASH');

        self::assertSame($paymentRequest, $this->dispatcher->dispatch($this->payment));
    }

    public function test_it_dispatches_the_open_charge_request_again_so_paypal_sees_the_same_request_id(): void
    {
        $paymentRequest = $this->paymentRequest(ChargeVaultedPaymentRequestCommandProvider::ACTION, 'OPEN_CHARGE_REQUEST_HASH');
        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([$paymentRequest]);

        $this->paymentRequestFactory->expects(self::never())->method('create');
        $this->stateMachine->expects(self::never())->method('apply');
        $this->paymentRequestRepository->expects(self::never())->method('add');
        $this->expectChargeDispatchedFor('OPEN_CHARGE_REQUEST_HASH');

        self::assertSame($paymentRequest, $this->dispatcher->dispatch($this->payment));
    }

    public function test_it_moves_an_open_charge_request_that_is_still_new_to_processing_before_dispatching_it(): void
    {
        $paymentRequest = $this->paymentRequest(ChargeVaultedPaymentRequestCommandProvider::ACTION, 'NEW_CHARGE_REQUEST_HASH', PaymentRequestInterface::STATE_NEW);
        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([$paymentRequest]);

        $this->paymentRequestFactory->expects(self::never())->method('create');
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        $this->expectChargeDispatchedFor('NEW_CHARGE_REQUEST_HASH');

        self::assertSame($paymentRequest, $this->dispatcher->dispatch($this->payment));
    }

    public function test_it_looks_for_the_open_charge_request_under_the_payment_lock_and_dispatches_after_the_commit(): void
    {
        $paymentRequest = $this->paymentRequest(ChargeVaultedPaymentRequestCommandProvider::ACTION, 'OPEN_CHARGE_REQUEST_HASH');
        $this->paymentRequestRepository
            ->method('findByPaymentIdAndStates')
            ->willReturnCallback(function () use ($paymentRequest): array {
                $this->calls[] = 'lookup';

                return [$paymentRequest];
            })
        ;

        $this->expectPaymentLocked();
        $this->recordDispatch();

        $this->dispatcher->dispatch($this->payment);

        self::assertSame(['begin', 'lock', 'lookup', 'commit', 'dispatch'], $this->calls);
    }

    public function test_it_creates_the_charge_request_under_the_payment_lock_and_dispatches_after_the_commit(): void
    {
        $paymentRequest = $this->paymentRequest(ChargeVaultedPaymentRequestCommandProvider::ACTION, 'CHARGE_REQUEST_HASH');
        $this->paymentRequestRepository
            ->method('findByPaymentIdAndStates')
            ->willReturnCallback(function (): array {
                $this->calls[] = 'lookup';

                return [];
            })
        ;
        $this->paymentRequestFactory->method('create')->willReturn($paymentRequest);
        $this->paymentRequestRepository
            ->method('add')
            ->willReturnCallback(function (): void {
                $this->calls[] = 'add';
            })
        ;

        $this->expectPaymentLocked();
        $this->recordDispatch();

        $this->dispatcher->dispatch($this->payment);

        self::assertSame(['begin', 'lock', 'lookup', 'add', 'commit', 'dispatch'], $this->calls);
    }

    private function expectPaymentLocked(): void
    {
        $this->paymentManager->expects(self::never())->method('refresh');
        $this->paymentManager
            ->expects(self::once())
            ->method('lock')
            ->with($this->payment, LockMode::PESSIMISTIC_WRITE)
            ->willReturnCallback(function (): void {
                $this->calls[] = 'lock';
            })
        ;
    }

    private function recordDispatch(): void
    {
        $this->commandBus
            ->method('dispatch')
            ->willReturnCallback(function (object $command): Envelope {
                $this->calls[] = 'dispatch';

                return new Envelope($command);
            })
        ;
    }

    private function paymentRequest(string $action, string $hash, string $state = PaymentRequestInterface::STATE_PROCESSING): PaymentRequestInterface&MockObject
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn($action);
        $paymentRequest->method('getId')->willReturn($hash);
        $paymentRequest->method('getState')->willReturn($state);

        return $paymentRequest;
    }

    private function expectChargeDispatchedFor(string $hash): void
    {
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (ChargeVaultedPaymentRequest $command): bool => $hash === $command->getHash()))
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command))
        ;
    }
}
