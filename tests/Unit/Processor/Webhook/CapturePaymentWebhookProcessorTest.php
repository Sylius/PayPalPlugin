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
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\Webhook\CapturePaymentWebhookProcessor;
use Sylius\PayPalPlugin\Processor\Webhook\WebhookProcessorInterface;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;

final class CapturePaymentWebhookProcessorTest extends TestCase
{
    private SettleablePaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private LoggerInterface&MockObject $logger;

    private CapturePaymentWebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paypalPaymentQuery = $this->createMock(SettleablePaypalPaymentQueryInterface::class);
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new CapturePaymentWebhookProcessor(
            $this->paypalPaymentQuery,
            $this->paymentSettlementProcessor,
            $this->logger,
        );
    }

    public function test_it_implements_webhook_processor_interface(): void
    {
        self::assertInstanceOf(WebhookProcessorInterface::class, $this->processor);
    }

    public function test_it_handles_every_capture_outcome_and_nothing_else(): void
    {
        self::assertTrue($this->processor->supports('PAYMENT.CAPTURE.COMPLETED'));
        self::assertTrue($this->processor->supports('PAYMENT.CAPTURE.DENIED'));
        self::assertTrue($this->processor->supports('PAYMENT.CAPTURE.DECLINED'));
        self::assertTrue($this->processor->supports('PAYMENT.CAPTURE.PENDING'));
        self::assertFalse($this->processor->supports('PAYMENT.CAPTURE.REFUNDED'));
        self::assertFalse($this->processor->supports('CHECKOUT.ORDER.APPROVED'));
    }

    public function test_it_settles_the_payment_the_capture_belongs_to(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $this->paypalPaymentQuery
            ->method('getForSettlementByOrderId')
            ->with('5O190127TN364715T')
            ->willReturn($payment)
        ;

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($payment);

        $this->processor->process($this->payload());
    }

    public function test_it_reads_the_payment_state_from_paypal_rather_than_from_the_event(): void
    {
        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->willReturn($this->createMock(PaymentInterface::class));

        $this->paymentSettlementProcessor
            ->expects(self::once())
            ->method('settle')
            ->with(self::anything(), null)
        ;

        $this->processor->process($this->payload());
    }

    public function test_it_stays_quiet_about_a_capture_of_an_order_it_does_not_know(): void
    {
        $this->paypalPaymentQuery
            ->method('getForSettlementByOrderId')
            ->willThrowException(new PaymentNotFoundException())
        ;

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');

        $this->processor->process($this->payload());
    }

    public function test_it_reports_a_capture_event_without_an_order_to_settle_against(): void
    {
        $this->logger->expects(self::once())->method('warning');
        $this->paypalPaymentQuery->expects(self::never())->method('getForSettlementByOrderId');

        $this->processor->process(['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAPTURE_ID']]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => '892032536L382192T',
                'status' => 'COMPLETED',
                'supplementary_data' => ['related_ids' => ['order_id' => '5O190127TN364715T']],
            ],
        ];
    }
}
