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
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Console\Command\CompletePaidPaymentsCommand;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentBatchQueryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CompletePaidPaymentsCommandTest extends TestCase
{
    /** @var PaymentRepositoryInterface<PaymentInterface>&MockObject */
    private PaymentRepositoryInterface&MockObject $paymentRepository;

    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);

        $this->commandTester = new CommandTester(
            new CompletePaidPaymentsCommand(
                $this->paymentRepository,
                paymentSettlementProcessor: $this->paymentSettlementProcessor,
            ),
        );
    }

    public function test_it_hands_every_processing_or_authorized_paypal_payment_to_the_settlement_processor(): void
    {
        $processing = $this->payment('sylius_paypal');
        $authorized = $this->payment('sylius_paypal');
        $this->paymentRepository
            ->method('findBy')
            ->with(['state' => [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED]])
            ->willReturn([$processing, $authorized])
        ;

        $settled = [];
        $this->paymentSettlementProcessor
            ->expects(self::exactly(2))
            ->method('settle')
            ->willReturnCallback(function (PaymentInterface $payment) use (&$settled): void {
                $settled[] = $payment;
            })
        ;

        self::assertSame(0, $this->commandTester->execute([]));
        self::assertSame([$processing, $authorized], $settled);
    }

    public function test_it_leaves_payments_of_other_gateways_alone(): void
    {
        $this->paymentRepository->method('findBy')->willReturn([$this->payment('stripe')]);

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');

        self::assertSame(0, $this->commandTester->execute([]));
    }

    public function test_it_no_longer_decides_on_its_own_whether_a_payment_is_paid(): void
    {
        $payment = $this->payment('sylius_paypal');
        $payment->expects(self::never())->method('setDetails');
        $this->paymentRepository->method('findBy')->willReturn([$payment]);

        $this->commandTester->execute([]);
    }

    public function test_it_reports_a_payment_that_could_not_be_settled_and_settles_the_next_ones(): void
    {
        $failing = $this->payment('sylius_paypal');
        $failing->method('getId')->willReturn(7);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('000000042');
        $failing->method('getOrder')->willReturn($order);
        $next = $this->payment('sylius_paypal');
        $this->paymentRepository->method('findBy')->willReturn([$failing, $next]);

        $settled = [];
        $this->paymentSettlementProcessor
            ->method('settle')
            ->willReturnCallback(function (PaymentInterface $payment) use ($failing, &$settled): void {
                if ($payment === $failing) {
                    throw new PayPalApiNotFoundException('GET', 'v2/checkout/orders/PAYPAL_ORDER_ID', 404, 'RESOURCE_NOT_FOUND');
                }

                $settled[] = $payment;
            })
        ;

        self::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        self::assertSame([$next], $settled);
        self::assertStringContainsString('Payment #7 of order #000000042 could not be settled', $this->commandTester->getDisplay());
    }

    public function test_it_walks_the_payments_batch_after_batch_and_clears_the_entity_manager_after_each_batch_when_it_is_given_the_batch_query(): void
    {
        $first = $this->payment('sylius_paypal');
        $first->method('getId')->willReturn(3);
        $second = $this->payment('sylius_paypal');
        $second->method('getId')->willReturn(5);
        $third = $this->payment('sylius_paypal');
        $third->method('getId')->willReturn(8);

        $states = [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED];
        $paypalPaymentBatchQuery = $this->createMock(PaypalPaymentBatchQueryInterface::class);
        $paypalPaymentBatchQuery
            ->expects(self::exactly(3))
            ->method('findInStates')
            ->willReturnMap([
                [$states, 2, null, [$first, $second]],
                [$states, 2, 5, [$third]],
                [$states, 2, 8, []],
            ])
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('clear');

        $settled = [];
        $this->paymentRepository->expects(self::never())->method('findBy');
        $this->paymentSettlementProcessor
            ->expects(self::exactly(3))
            ->method('settle')
            ->willReturnCallback(static function (PaymentInterface $payment) use (&$settled): void {
                $settled[] = $payment;
            })
        ;

        $commandTester = new CommandTester(new CompletePaidPaymentsCommand(
            $this->paymentRepository,
            paymentSettlementProcessor: $this->paymentSettlementProcessor,
            paypalPaymentBatchQuery: $paypalPaymentBatchQuery,
            entityManager: $entityManager,
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--batch-size' => '2']));
        self::assertSame([$first, $second, $third], $settled);
    }

    public function test_it_moves_past_a_payment_it_cannot_settle_in_a_batch(): void
    {
        $failing = $this->payment('sylius_paypal');
        $failing->method('getId')->willReturn(4);
        $failing->method('getOrder')->willReturn(null);

        $states = [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED];
        $paypalPaymentBatchQuery = $this->createMock(PaypalPaymentBatchQueryInterface::class);
        $paypalPaymentBatchQuery
            ->expects(self::exactly(2))
            ->method('findInStates')
            ->willReturnMap([
                [$states, 100, null, [$failing]],
                [$states, 100, 4, []],
            ])
        ;

        $this->paymentSettlementProcessor->method('settle')->willThrowException(new \RuntimeException('PayPal is down'));

        $commandTester = new CommandTester(new CompletePaidPaymentsCommand(
            $this->paymentRepository,
            paymentSettlementProcessor: $this->paymentSettlementProcessor,
            paypalPaymentBatchQuery: $paypalPaymentBatchQuery,
        ));

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('Payment #4 of order # could not be settled: PayPal is down', $commandTester->getDisplay());
    }

    private function payment(string $factoryName): PaymentInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        return $payment;
    }

    public function test_it_still_settles_for_an_application_that_passes_the_released_arguments(): void
    {
        $payment = $this->payment('sylius_paypal');
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID']);
        $this->paymentRepository->method('findBy')->willReturn([$payment]);

        $orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $orderDetailsApi->expects(self::once())->method('get')->with($payment->getMethod(), 'PAYPAL_ORDER_ID')->willReturn([]);

        $commandTester = new CommandTester(new CompletePaidPaymentsCommand(
            $this->paymentRepository,
            $this->createMock(ObjectManager::class),
            $this->createMock(CacheAuthorizeClientApiInterface::class),
            $orderDetailsApi,
            $this->createMock(StateMachineInterface::class),
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
    }

    public function test_it_settles_nothing_when_it_was_given_nothing_to_settle_with(): void
    {
        $commandTester = new CommandTester(new CompletePaidPaymentsCommand($this->paymentRepository));

        $this->paymentRepository->expects(self::never())->method('findBy');

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
    }
}
