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

namespace Tests\Sylius\PayPalPlugin\Unit\Console\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Console\Command\CompletePaidPaymentsCommand;
use Sylius\PayPalPlugin\Dispatcher\StatusPaymentRequestDispatcherInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentBatchQueryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CompletePaidPaymentsCommandTest extends TestCase
{
    private const SETTLEABLE_STATES = [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED];

    private PaypalPaymentBatchQueryInterface&MockObject $paypalPaymentBatchQuery;

    private StatusPaymentRequestDispatcherInterface&MockObject $statusPaymentRequestDispatcher;

    private EntityManagerInterface&MockObject $entityManager;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->paypalPaymentBatchQuery = $this->createMock(PaypalPaymentBatchQueryInterface::class);
        $this->statusPaymentRequestDispatcher = $this->createMock(StatusPaymentRequestDispatcherInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->commandTester = new CommandTester(new CompletePaidPaymentsCommand(
            $this->paypalPaymentBatchQuery,
            $this->statusPaymentRequestDispatcher,
            $this->entityManager,
        ));
    }

    public function test_it_dispatches_a_status_payment_request_for_every_payment_batch_after_batch(): void
    {
        $first = $this->payment(3);
        $second = $this->payment(5);
        $third = $this->payment(8);
        $this->paypalPaymentBatchQuery
            ->expects(self::exactly(3))
            ->method('findInStates')
            ->willReturnMap([
                [self::SETTLEABLE_STATES, 2, null, [$first, $second]],
                [self::SETTLEABLE_STATES, 2, 5, [$third]],
                [self::SETTLEABLE_STATES, 2, 8, []],
            ])
        ;

        $settled = [];
        $this->statusPaymentRequestDispatcher
            ->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function (PaymentInterface $payment) use (&$settled): PaymentRequestInterface {
                $settled[] = $payment;

                return $this->paymentRequest(PaymentRequestInterface::STATE_COMPLETED);
            })
        ;
        $this->entityManager->expects(self::exactly(2))->method('clear');

        self::assertSame(Command::SUCCESS, $this->commandTester->execute(['--batch-size' => '2']));
        self::assertSame([$first, $second, $third], $settled);
    }

    public function test_it_reports_a_payment_whose_status_payment_request_failed_and_settles_the_next_ones(): void
    {
        $failing = $this->payment(7, '000000042');
        $next = $this->payment(9);
        $this->batches([$failing, $next]);

        $this->statusPaymentRequestDispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(fn (PaymentInterface $payment): PaymentRequestInterface => $payment === $failing
                ? $this->paymentRequest(PaymentRequestInterface::STATE_FAILED, ['reason' => 'PayPal could not return the order.'])
                : $this->paymentRequest(PaymentRequestInterface::STATE_COMPLETED))
        ;

        self::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        self::assertStringContainsString('Payment #7 of order #000000042 could not be settled: PayPal could not return the order.', $this->display());
    }

    public function test_it_reports_a_payment_whose_status_payment_request_could_not_be_dispatched(): void
    {
        $this->batches([$this->payment(4)]);

        $this->statusPaymentRequestDispatcher->method('dispatch')->willThrowException(new \RuntimeException('The database is down.'));

        self::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        self::assertStringContainsString('Payment #4 of order # could not be settled: The database is down.', $this->display());
    }

    public function test_it_leaves_a_payment_whose_status_payment_request_is_still_being_handled_alone(): void
    {
        $this->batches([$this->payment(4)]);

        $this->statusPaymentRequestDispatcher->method('dispatch')->willReturn($this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING));

        self::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        self::assertSame('', $this->commandTester->getDisplay());
    }

    /** @param list<PaymentInterface> $payments */
    private function batches(array $payments): void
    {
        $lastId = $payments[array_key_last($payments)]->getId();
        $this->paypalPaymentBatchQuery->method('findInStates')->willReturnMap([
            [self::SETTLEABLE_STATES, 100, null, $payments],
            [self::SETTLEABLE_STATES, 100, $lastId, []],
        ]);
    }

    private function payment(int $id, ?string $orderNumber = null): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn($id);
        if (null !== $orderNumber) {
            $order = $this->createMock(OrderInterface::class);
            $order->method('getNumber')->willReturn($orderNumber);
            $payment->method('getOrder')->willReturn($order);
        }

        return $payment;
    }

    /** @param array<string, mixed> $responseData */
    private function paymentRequest(string $state, array $responseData = []): PaymentRequestInterface
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getState')->willReturn($state);
        $paymentRequest->method('getResponseData')->willReturn($responseData);

        return $paymentRequest;
    }

    private function display(): string
    {
        return preg_replace('/\s+/', ' ', $this->commandTester->getDisplay()) ?? '';
    }
}
