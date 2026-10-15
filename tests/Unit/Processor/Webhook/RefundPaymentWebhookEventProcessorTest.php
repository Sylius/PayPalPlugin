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

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Processor\Webhook\RefundPaymentWebhookEventProcessor;

final class RefundPaymentWebhookEventProcessorTest extends TestCase
{
    private PayPalPaymentRefundConfirmationProcessorInterface&MockObject $refundConfirmationProcessor;

    private ObjectManager&MockObject $paymentManager;

    private LoggerInterface&MockObject $logger;

    private RefundPaymentWebhookEventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refundConfirmationProcessor = $this->createMock(PayPalPaymentRefundConfirmationProcessorInterface::class);
        $this->paymentManager = $this->createMock(ObjectManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new RefundPaymentWebhookEventProcessor($this->refundConfirmationProcessor, $this->paymentManager, $this->logger);
    }

    public function test_it_confirms_the_refund_of_a_payment_paypal_refunded(): void
    {
        $payment = $this->payment([]);

        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($payment, 'REFUND_ID');
        $this->paymentManager->expects(self::once())->method('flush');
        $this->logger->expects(self::never())->method('warning');

        $this->processor->process('PAYMENT.CAPTURE.REFUNDED', $this->refund(), $payment);
    }

    public function test_it_confirms_the_refund_of_a_payment_paypal_reversed_and_reports_the_reversal(): void
    {
        $payment = $this->payment([]);

        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($payment, 'REFUND_ID');
        $this->logger->expects(self::once())->method('warning');

        $this->processor->process('PAYMENT.CAPTURE.REVERSED', $this->refund(), $payment);
    }

    public function test_it_does_not_report_the_reversal_of_a_payment_already_refunded(): void
    {
        $payment = $this->payment([]);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_REFUNDED);

        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($payment, 'REFUND_ID');
        $this->logger->expects(self::never())->method('warning');

        $this->processor->process('PAYMENT.CAPTURE.REVERSED', $this->refund(), $payment);
    }

    public function test_it_marks_the_late_capture_it_refunded_instead_of_refunding_the_payment(): void
    {
        $payment = $this->payment(['paypal_late_capture' => ['id' => 'CAPTURE_ID', 'amount' => 20]]);

        $payment->expects(self::once())->method('setDetails')->with([
            'paypal_late_capture' => ['id' => 'CAPTURE_ID', 'amount' => 20, 'refunded' => true, 'refund_id' => 'REFUND_ID'],
        ]);
        $this->paymentManager->expects(self::once())->method('flush');
        $this->refundConfirmationProcessor->expects(self::never())->method('confirm');

        $this->processor->process('PAYMENT.CAPTURE.REFUNDED', $this->refund(), $payment);
    }

    public function test_it_gives_up_on_a_refund_that_does_not_point_at_its_capture(): void
    {
        $this->expectException(PayPalWrongDataException::class);

        $this->processor->process('PAYMENT.CAPTURE.REFUNDED', ['id' => 'REFUND_ID', 'links' => []], $this->payment([]));
    }

    /** @return array<string, mixed> */
    private function refund(): array
    {
        return [
            'id' => 'REFUND_ID',
            'links' => [
                ['rel' => 'self', 'href' => 'https://api.paypal.com/v2/payments/refunds/REFUND_ID'],
                ['rel' => 'up', 'href' => 'https://api.paypal.com/v2/payments/captures/CAPTURE_ID'],
            ],
        ];
    }

    /** @param array<string, mixed> $details */
    private function payment(array $details): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($details);
        $payment->method('getId')->willReturn(7);

        return $payment;
    }
}
