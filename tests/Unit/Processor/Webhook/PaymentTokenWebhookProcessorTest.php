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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor\Webhook;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Payment;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenDeleted;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Processor\Webhook\PaymentTokenWebhookProcessor;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Repository\Query\VaultedPaypalPaymentQueryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PaymentTokenWebhookProcessorTest extends TestCase
{
    private SettleablePaypalPaymentQueryInterface&VaultedPaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private EntityManagerInterface&MockObject $paymentManager;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private PaymentTokenWebhookProcessor $processor;

    protected function setUp(): void
    {
        $this->paypalPaymentQuery = $this->createMockForIntersectionOfInterfaces([SettleablePaypalPaymentQueryInterface::class, VaultedPaypalPaymentQueryInterface::class]);
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $func): mixed => $func());
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->processor = new PaymentTokenWebhookProcessor($this->paypalPaymentQuery, $this->paymentManager, $this->eventDispatcher);
    }

    public function test_it_supports_the_created_and_deleted_payment_token_events_only(): void
    {
        self::assertTrue($this->processor->supports('VAULT.PAYMENT-TOKEN.CREATED'));
        self::assertTrue($this->processor->supports('VAULT.PAYMENT-TOKEN.DELETED'));
        self::assertFalse($this->processor->supports('VAULT.PAYMENT-TOKEN.DELETION-INITIATED'));
        self::assertFalse($this->processor->supports('PAYMENT.CAPTURE.COMPLETED'));
    }

    public function test_it_records_the_token_paypal_saved_after_the_payment_on_the_payment(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_status' => 'APPROVED', 'vault_customer_id' => 'CUSTOMER_ID', 'payer_email' => 'buyer@example.com']);

        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);
        $this->paymentManager->expects(self::once())->method('refresh')->with($payment, LockMode::PESSIMISTIC_WRITE);
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (PayPalPaymentTokenCreated $event): bool => $payment === $event->payment && 'VAULT_ID' === $event->vaultId))
            ->willReturnArgument(0)
        ;

        $this->processor->process($this->created(['id' => 'VAULT_ID', 'customer' => ['id' => 'CUSTOMER_ID'], 'metadata' => ['order_id' => 'PAYPAL_ORDER_ID']]));

        self::assertSame([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'vault_status' => 'VAULTED',
            'vault_customer_id' => 'CUSTOMER_ID',
            'payer_email' => 'buyer@example.com',
            'vault_id' => 'VAULT_ID',
        ], $payment->getDetails());
    }

    public function test_it_leaves_a_payment_that_already_knows_its_token_alone(): void
    {
        $payment = new Payment();
        $payment->setDetails(['vault_id' => 'VAULT_ID', 'vault_status' => 'VAULTED']);

        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->willReturn($payment);
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $this->processor->process($this->created(['id' => 'VAULT_ID', 'metadata' => ['order_id' => 'PAYPAL_ORDER_ID']]));

        self::assertSame(['vault_id' => 'VAULT_ID', 'vault_status' => 'VAULTED'], $payment->getDetails());
    }

    public function test_it_reads_the_payment_again_under_a_lock_before_recording_the_token(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_status' => 'APPROVED']);

        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->willReturn($payment);
        $this->paymentManager->method('refresh')->willReturnCallback(static function (Payment $payment): void {
            $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_id' => 'CAPTURED_VAULT_ID', 'vault_status' => 'VAULTED']);
        });
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $this->processor->process($this->created(['id' => 'VAULT_ID', 'metadata' => ['order_id' => 'PAYPAL_ORDER_ID']]));

        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_id' => 'CAPTURED_VAULT_ID', 'vault_status' => 'VAULTED'], $payment->getDetails());
    }

    public function test_it_ignores_a_created_token_of_no_known_payment(): void
    {
        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->willThrowException(new PaymentNotFoundException());
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $this->processor->process($this->created(['id' => 'VAULT_ID', 'metadata' => ['order_id' => 'UNKNOWN_ORDER_ID']]));
    }

    public function test_it_ignores_a_token_saved_without_a_purchase(): void
    {
        $this->paypalPaymentQuery->expects(self::never())->method('getForSettlementByOrderId');
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $this->processor->process($this->created(['id' => 'VAULT_ID']));
    }

    public function test_it_forgets_a_deleted_token_on_the_payments_and_announces_it(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_id' => 'VAULT_ID', 'vault_status' => 'VAULTED', 'vault_customer_id' => 'CUSTOMER_ID']);

        $this->paypalPaymentQuery->expects(self::never())->method('getForSettlementByOrderId');
        $this->paypalPaymentQuery->method('findByVaultId')->with('VAULT_ID')->willReturn([$payment]);
        $this->paymentManager->expects(self::once())->method('flush');
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (PayPalPaymentTokenDeleted $event): bool => 'VAULT_ID' === $event->vaultId))
            ->willReturnArgument(0)
        ;

        $this->processor->process(['event_type' => 'VAULT.PAYMENT-TOKEN.DELETED', 'resource' => ['id' => 'VAULT_ID']]);

        self::assertSame([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'vault_status' => 'DELETED',
            'vault_customer_id' => 'CUSTOMER_ID',
        ], $payment->getDetails());
    }

    public function test_it_announces_a_deleted_token_no_payment_knows(): void
    {
        $this->paypalPaymentQuery->method('findByVaultId')->willReturn([]);
        $this->paymentManager->expects(self::never())->method('flush');
        $this->eventDispatcher->expects(self::once())->method('dispatch')->willReturnArgument(0);

        $this->processor->process(['event_type' => 'VAULT.PAYMENT-TOKEN.DELETED', 'resource' => ['id' => 'VAULT_ID']]);
    }

    public function test_it_ignores_an_event_without_a_token_id(): void
    {
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $this->processor->process(['event_type' => 'VAULT.PAYMENT-TOKEN.DELETED', 'resource' => []]);
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    private function created(array $resource): array
    {
        return ['event_type' => 'VAULT.PAYMENT-TOKEN.CREATED', 'resource' => $resource];
    }
}
