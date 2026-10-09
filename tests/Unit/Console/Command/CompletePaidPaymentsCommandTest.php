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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Console\Command\CompletePaidPaymentsCommand;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Symfony\Component\Console\Tester\CommandTester;

final class CompletePaidPaymentsCommandTest extends TestCase
{
    /** @var PaymentRepositoryInterface<PaymentInterface>&MockObject */
    private PaymentRepositoryInterface&MockObject $paymentRepository;

    private ObjectManager&MockObject $paymentManager;

    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private StateMachineInterface&MockObject $stateMachine;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->paymentManager = $this->createMock(ObjectManager::class);
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);

        $this->commandTester = new CommandTester(new CompletePaidPaymentsCommand(
            $this->paymentRepository,
            $this->paymentManager,
            $this->authorizeClientApi,
            $this->orderDetailsApi,
            $this->stateMachine,
        ));
    }

    #[Test]
    public function it_completes_processing_paypal_payments_whose_order_is_completed_on_paypal(): void
    {
        $payment = $this->createPayPalPayment(['paypal_order_id' => 'PP-ORDER-123']);

        $this->paymentRepository
            ->method('findBy')
            ->with(['state' => PaymentInterface::STATE_PROCESSING])
            ->willReturn([$payment])
        ;

        $this->authorizeClientApi->method('authorize')->willReturn('auth-token');
        $this->orderDetailsApi
            ->method('get')
            ->with('auth-token', 'PP-ORDER-123')
            ->willReturn(['id' => 'PP-ORDER-123', 'status' => 'COMPLETED'])
        ;

        $payment
            ->expects($this->once())
            ->method('setDetails')
            ->with($this->callback(static fn (array $details): bool => ($details['status'] ?? null) === StatusAction::STATUS_COMPLETED &&
                ($details['paypal_order_id'] ?? null) === 'PP-ORDER-123'))
        ;

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)
        ;

        $this->paymentManager->expects($this->once())->method('flush');

        $this->assertSame(0, $this->commandTester->execute([]));
    }

    #[Test]
    public function it_skips_payments_whose_paypal_order_returned_an_error_payload_without_status_key(): void
    {
        // Reproduces the real-world case where a PayPal order that was never
        // captured expires after ~3h; subsequent GET /v2/checkout/orders/{id}
        // calls return a RESOURCE_NOT_FOUND error payload. Before this fix the
        // command emitted a PHP warning on every hourly cron tick and the stale
        // payment stayed processing forever.
        $payment = $this->createPayPalPayment(['paypal_order_id' => 'PP-EXPIRED-ORDER']);

        $this->paymentRepository->method('findBy')->willReturn([$payment]);
        $this->authorizeClientApi->method('authorize')->willReturn('auth-token');
        $this->orderDetailsApi
            ->method('get')
            ->willReturn([
                'name' => 'RESOURCE_NOT_FOUND',
                'message' => 'The specified resource does not exist.',
                'debug_id' => 'abc123def456',
            ])
        ;

        $this->stateMachine->expects($this->never())->method('apply');
        $payment->expects($this->never())->method('setDetails');
        $this->paymentManager->expects($this->once())->method('flush');

        $previous = set_error_handler(static function (int $severity, string $message): bool {
            if (str_contains($message, 'Undefined array key')) {
                throw new \AssertionError('Unexpected PHP warning: ' . $message);
            }

            return false;
        });

        try {
            $this->assertSame(0, $this->commandTester->execute([]));
        } finally {
            set_error_handler($previous);
        }
    }

    #[Test]
    public function it_does_not_complete_payments_whose_paypal_order_is_not_completed_yet(): void
    {
        $payment = $this->createPayPalPayment(['paypal_order_id' => 'PP-ORDER-APPROVED']);

        $this->paymentRepository->method('findBy')->willReturn([$payment]);
        $this->authorizeClientApi->method('authorize')->willReturn('auth-token');
        $this->orderDetailsApi
            ->method('get')
            ->willReturn(['id' => 'PP-ORDER-APPROVED', 'status' => 'APPROVED'])
        ;

        $this->stateMachine->expects($this->never())->method('apply');
        $payment->expects($this->never())->method('setDetails');
        $this->paymentManager->expects($this->once())->method('flush');

        $this->assertSame(0, $this->commandTester->execute([]));
    }

    #[Test]
    public function it_skips_processing_payments_handled_by_a_non_paypal_gateway(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $payment->method('getMethod')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('stripe');

        $this->paymentRepository->method('findBy')->willReturn([$payment]);

        $this->authorizeClientApi->expects($this->never())->method('authorize');
        $this->orderDetailsApi->expects($this->never())->method('get');
        $this->stateMachine->expects($this->never())->method('apply');

        $this->assertSame(0, $this->commandTester->execute([]));
    }

    /**
     * @param array<string, mixed> $details
     */
    private function createPayPalPayment(array $details): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn($details);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);

        return $payment;
    }
}
