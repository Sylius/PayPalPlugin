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
use Sylius\PayPalPlugin\Command\AnnouncePayPalPaymentToken;
use Sylius\PayPalPlugin\Processor\Webhook\PayloadResourceWebhookEventProcessorInterface;
use Sylius\PayPalPlugin\Processor\Webhook\PaymentTokenCreatedWebhookEventProcessor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final class PaymentTokenCreatedWebhookEventProcessorTest extends TestCase
{
    private EntityManagerInterface&MockObject $paymentManager;

    private MessageBusInterface&MockObject $commandBus;

    private PaymentTokenCreatedWebhookEventProcessor $processor;

    protected function setUp(): void
    {
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);

        $this->processor = new PaymentTokenCreatedWebhookEventProcessor($this->paymentManager, $this->commandBus);
    }

    public function test_it_processes_the_token_from_the_signed_payload(): void
    {
        self::assertInstanceOf(PayloadResourceWebhookEventProcessorInterface::class, $this->processor);
    }

    public function test_it_records_the_token_paypal_saved_during_the_payment_and_announces_it_once_stored(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_status' => 'APPROVED', 'vault_customer_id' => 'CUSTOMER_ID', 'payer_email' => 'buyer@example.com']);

        $this->paymentManager->expects(self::once())->method('refresh')->with($payment, LockMode::PESSIMISTIC_WRITE);
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (AnnouncePayPalPaymentToken $command): bool => 'VAULT_ID' === $command->vaultId),
                self::callback(static fn (array $stamps): bool => [new DispatchAfterCurrentBusStamp()] == $stamps),
            )
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command))
        ;

        $this->processor->process(PaymentTokenCreatedWebhookEventProcessor::EVENT_TYPE, ['id' => 'VAULT_ID', 'customer' => ['id' => 'CUSTOMER_ID']], $payment);

        self::assertSame([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'vault_status' => 'VAULTED',
            'vault_customer_id' => 'CUSTOMER_ID',
            'payer_email' => 'buyer@example.com',
            'vault_id' => 'VAULT_ID',
        ], $payment->getDetails());
    }

    public function test_it_leaves_a_payment_the_capture_gave_a_token_while_it_waited_for_the_lock_alone(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_status' => 'APPROVED']);
        $this->paymentManager->method('refresh')->willReturnCallback(static function (Payment $payment): void {
            $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_id' => 'CAPTURED_VAULT_ID', 'vault_status' => 'VAULTED']);
        });

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process(PaymentTokenCreatedWebhookEventProcessor::EVENT_TYPE, ['id' => 'VAULT_ID'], $payment);

        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_id' => 'CAPTURED_VAULT_ID', 'vault_status' => 'VAULTED'], $payment->getDetails());
    }

    public function test_it_ignores_an_event_without_a_token_id(): void
    {
        $this->paymentManager->expects(self::never())->method('refresh');
        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process(PaymentTokenCreatedWebhookEventProcessor::EVENT_TYPE, [], new Payment());
    }
}
