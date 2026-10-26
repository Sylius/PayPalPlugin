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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Payment;
use Sylius\PayPalPlugin\Command\AnnouncePayPalPaymentTokenDeletion;
use Sylius\PayPalPlugin\Processor\Webhook\PayloadResourceWebhookEventProcessorInterface;
use Sylius\PayPalPlugin\Processor\Webhook\PaymentTokenDeletedWebhookEventProcessor;
use Sylius\PayPalPlugin\Repository\Query\VaultedPaypalPaymentQueryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final class PaymentTokenDeletedWebhookEventProcessorTest extends TestCase
{
    private VaultedPaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private MessageBusInterface&MockObject $commandBus;

    private PaymentTokenDeletedWebhookEventProcessor $processor;

    protected function setUp(): void
    {
        $this->paypalPaymentQuery = $this->createMock(VaultedPaypalPaymentQueryInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);

        $this->processor = new PaymentTokenDeletedWebhookEventProcessor($this->paypalPaymentQuery, $this->commandBus);
    }

    public function test_it_processes_the_token_from_the_signed_payload_paypal_can_no_longer_be_asked_about(): void
    {
        self::assertInstanceOf(PayloadResourceWebhookEventProcessorInterface::class, $this->processor);
    }

    public function test_it_forgets_the_deleted_token_on_every_payment_that_carries_it_and_announces_it_once_stored(): void
    {
        $payment = new Payment();
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_id' => 'VAULT_ID', 'vault_status' => 'VAULTED', 'vault_customer_id' => 'CUSTOMER_ID']);
        $laterPayment = new Payment();
        $laterPayment->setDetails(['vault_id' => 'VAULT_ID', 'vault_status' => 'VAULTED']);
        $this->paypalPaymentQuery->method('findByVaultId')->with('VAULT_ID')->willReturn([$payment, $laterPayment]);

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (AnnouncePayPalPaymentTokenDeletion $command): bool => 'VAULT_ID' === $command->vaultId),
                self::callback(static fn (array $stamps): bool => [new DispatchAfterCurrentBusStamp()] == $stamps),
            )
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command))
        ;

        $this->processor->process(PaymentTokenDeletedWebhookEventProcessor::EVENT_TYPE, ['id' => 'VAULT_ID'], $payment);

        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'vault_status' => 'DELETED', 'vault_customer_id' => 'CUSTOMER_ID'], $payment->getDetails());
        self::assertSame(['vault_status' => 'DELETED'], $laterPayment->getDetails());
    }

    public function test_it_ignores_an_event_without_a_token_id(): void
    {
        $this->paypalPaymentQuery->expects(self::never())->method('findByVaultId');
        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process(PaymentTokenDeletedWebhookEventProcessor::EVENT_TYPE, [], new Payment());
    }
}
