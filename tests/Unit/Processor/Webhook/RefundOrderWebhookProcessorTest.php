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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Processor\Webhook\RefundOrderWebhookProcessor;
use Sylius\PayPalPlugin\Processor\Webhook\WebhookProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;

final class RefundOrderWebhookProcessorTest extends TestCase
{
    private PayPalRefundDataProviderInterface&MockObject $payPalRefundDataProvider;

    private PaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private PayPalPaymentRefundConfirmationProcessorInterface&MockObject $refundConfirmationProcessor;

    private ObjectManager&MockObject $paymentManager;

    private SettleablePaypalPaymentQueryInterface&MockObject $settleablePaymentQuery;

    private RefundOrderWebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalRefundDataProvider = $this->createMock(PayPalRefundDataProviderInterface::class);
        $this->paypalPaymentQuery = $this->createMock(PaypalPaymentQueryInterface::class);
        $this->refundConfirmationProcessor = $this->createMock(PayPalPaymentRefundConfirmationProcessorInterface::class);
        $this->paymentManager = $this->createMock(ObjectManager::class);
        $this->settleablePaymentQuery = $this->createMock(SettleablePaypalPaymentQueryInterface::class);

        $this->processor = new RefundOrderWebhookProcessor(
            $this->payPalRefundDataProvider,
            $this->paypalPaymentQuery,
            $this->refundConfirmationProcessor,
            $this->paymentManager,
            $this->settleablePaymentQuery,
        );
    }

    public function test_it_implements_webhook_processor_interface(): void
    {
        self::assertInstanceOf(WebhookProcessorInterface::class, $this->processor);
    }

    public function test_it_handles_refunds_and_nothing_else(): void
    {
        self::assertTrue($this->processor->supports('PAYMENT.CAPTURE.REFUNDED'));
        self::assertFalse($this->processor->supports('PAYMENT.CAPTURE.COMPLETED'));
    }

    public function test_it_refunds_the_payment_behind_the_up_link(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->payPalRefundDataProvider
            ->expects(self::once())
            ->method('provide')
            ->with('https://api-m.paypal.com/v2/checkout/orders/PAYPAL_ORDER_ID')
            ->willReturn(['id' => 'PAYPAL_ORDER_ID'])
        ;
        $this->paypalPaymentQuery->method('getForRefundingByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);

        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($payment, 'REFUND_ID');
        $this->paymentManager->expects(self::once())->method('flush');

        $this->processor->process($this->payload());
    }

    public function test_it_stays_quiet_about_a_refund_of_an_order_it_does_not_know(): void
    {
        $this->payPalRefundDataProvider->method('provide')->willReturn(['id' => 'PAYPAL_ORDER_ID']);
        $this->paypalPaymentQuery
            ->method('getForRefundingByOrderId')
            ->willThrowException(new PaymentNotFoundException())
        ;

        $this->refundConfirmationProcessor->expects(self::never())->method('confirm');

        $this->processor->process($this->payload());
    }

    public function test_it_records_the_refund_of_a_late_capture_without_refunding_the_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'paypal_late_capture' => ['id' => 'LATE_CAPTURE_ID', 'order_id' => 'SUPERSEDED_ORDER_ID', 'amount' => 1539, 'currency_code' => 'EUR'],
        ]);
        $this->payPalRefundDataProvider->method('provide')->with('https://api-m.paypal.com/v2/payments/captures/LATE_CAPTURE_ID')->willReturn(['id' => 'SUPERSEDED_ORDER_ID']);
        $this->settleablePaymentQuery->method('getForSettlementByOrderId')->with('SUPERSEDED_ORDER_ID')->willReturn($payment);

        $payment->expects(self::once())->method('setDetails')->with([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'paypal_late_capture' => ['id' => 'LATE_CAPTURE_ID', 'order_id' => 'SUPERSEDED_ORDER_ID', 'amount' => 1539, 'currency_code' => 'EUR', 'refunded' => true, 'refund_id' => 'REFUND_ID'],
        ]);
        $this->paymentManager->expects(self::once())->method('flush');
        $this->paypalPaymentQuery->expects(self::never())->method('getForRefundingByOrderId');
        $this->refundConfirmationProcessor->expects(self::never())->method('confirm');

        $this->processor->process($this->payload('https://api-m.paypal.com/v2/payments/captures/LATE_CAPTURE_ID'));
    }

    public function test_it_refunds_the_payment_when_paypal_refunded_another_capture_than_the_late_one(): void
    {
        $latePayment = $this->createMock(PaymentInterface::class);
        $latePayment->method('getDetails')->willReturn(['paypal_late_capture' => ['id' => 'LATE_CAPTURE_ID']]);
        $payment = $this->createMock(PaymentInterface::class);
        $this->payPalRefundDataProvider->method('provide')->willReturn(['id' => 'PAYPAL_ORDER_ID']);
        $this->settleablePaymentQuery->method('getForSettlementByOrderId')->willReturn($latePayment);
        $this->paypalPaymentQuery->method('getForRefundingByOrderId')->willReturn($payment);

        $latePayment->expects(self::never())->method('setDetails');
        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($payment, 'REFUND_ID');

        $this->processor->process($this->payload('https://api-m.paypal.com/v2/payments/captures/CAPTURE_ID'));
    }

    public function test_it_refuses_a_refund_event_without_an_up_link(): void
    {
        $this->expectException(PayPalWrongDataException::class);

        $this->processor->process(['resource' => ['links' => [['rel' => 'self', 'href' => 'https://example.com']]]]);
    }

    /** @return array<string, mixed> */
    private function payload(string $upLink = 'https://api-m.paypal.com/v2/checkout/orders/PAYPAL_ORDER_ID'): array
    {
        return [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'REFUND_ID',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.paypal.com/v2/payments/refunds/REFUND_ID'],
                    ['rel' => 'up', 'href' => $upLink],
                ],
            ],
        ];
    }
}
