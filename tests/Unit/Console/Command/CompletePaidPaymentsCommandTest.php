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

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Console\Command\CompletePaidPaymentsCommand;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
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

    public function test_it_hands_every_processing_paypal_payment_to_the_settlement_processor(): void
    {
        $payment = $this->payment('sylius_paypal');
        $this->paymentRepository
            ->method('findBy')
            ->with(['state' => PaymentInterface::STATE_PROCESSING])
            ->willReturn([$payment])
        ;

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($payment);

        self::assertSame(0, $this->commandTester->execute([]));
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

        $authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $authorizeClientApi->method('authorize')->willReturn('TOKEN');

        $orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $orderDetailsApi->expects(self::once())->method('get')->with('TOKEN', 'PAYPAL_ORDER_ID')->willReturn([]);

        $commandTester = new CommandTester(new CompletePaidPaymentsCommand(
            $this->paymentRepository,
            $this->createMock(ObjectManager::class),
            $authorizeClientApi,
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
