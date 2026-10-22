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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderChecker;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessor;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;

final class PaymentCaptureProcessorTest extends TestCase
{
    private const ORDER_DETAILS = [
        'status' => 'COMPLETED',
        'id' => '123123',
        'purchase_units' => [['reference_id' => 'REFERENCE_ID', 'payments' => ['captures' => [['id' => 'TRANSACTION_ID', 'status' => 'COMPLETED']]]]],
    ];

    private const APPROVED_ORDER = ['status' => 'APPROVED', 'id' => '123123', 'purchase_units' => [['reference_id' => 'REFERENCE_ID']]];

    private ApprovedPayPalOrderUpdaterInterface&MockObject $approvedPayPalOrderUpdater;

    private CompleteOrderApiInterface&MockObject $completeOrderApi;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private OrderInterface&MockObject $order;

    private PaymentInterface&MockObject $payment;

    private PaymentCaptureProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approvedPayPalOrderUpdater = $this->createMock(ApprovedPayPalOrderUpdaterInterface::class);
        $this->completeOrderApi = $this->createMock(CompleteOrderApiInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->payment->method('getMethod')->willReturn($this->paymentMethod);
        $this->payment->method('getOrder')->willReturn($this->order);

        $this->processor = new PaymentCaptureProcessor(
            new ApprovedPayPalOrderChecker(),
            $this->approvedPayPalOrderUpdater,
            $this->completeOrderApi,
            $this->orderDetailsApi,
        );
    }

    public function test_it_implements_payment_capture_processor_interface(): void
    {
        self::assertInstanceOf(PaymentCaptureProcessorInterface::class, $this->processor);
    }

    public function test_it_captures_the_paypal_order_and_records_it_on_the_payment(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1000);

        $this->completeOrderApi->expects(self::once())->method('complete')->with($this->paymentMethod, '123123');
        $this->paypalReadsTheApprovedOrderThen(self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => PayPalPaymentStatus::Completed->value,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'paypal',
            'transaction_id' => 'TRANSACTION_ID',
        ]);

        self::assertSame(self::ORDER_DETAILS, $this->processor->capture($this->payment));
    }

    public function test_it_captures_the_paypal_order_under_the_request_id_given_by_the_caller(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1000);
        $this->paypalReadsTheApprovedOrderThen(self::ORDER_DETAILS);

        $this->completeOrderApi->expects(self::once())->method('complete')->with($this->paymentMethod, '123123', 'PAYMENT_REQUEST_HASH-capture');

        $this->processor->capture($this->payment, 'PAYMENT_REQUEST_HASH-capture');
    }

    public function test_it_records_a_capture_paypal_has_not_completed_yet_as_processing(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123'], amount: 1000, total: 1000);

        $this->paypalReadsTheApprovedOrderThen(['status' => 'APPROVED'] + self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails')->with(self::callback(
            fn (array $details): bool => PayPalPaymentStatus::Processing->value === $details['status'],
        ));

        $this->processor->capture($this->payment);
    }

    public function test_it_leaves_the_payment_details_alone_when_paypal_captured_nothing(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123'], amount: 1000, total: 1000);

        $this->paypalReadsTheApprovedOrderThen(self::APPROVED_ORDER);
        $this->payment->expects(self::never())->method('setDetails');

        self::assertSame(self::APPROVED_ORDER, $this->processor->capture($this->payment));
    }

    #[DataProvider('ordersThePayerHasNotApproved')]
    public function test_it_does_not_capture_a_paypal_order_the_payer_has_not_approved(string $paymentSource, string $status): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID', 'payment_source' => $paymentSource], amount: 1000, total: 1200);
        $notApproved = ['status' => $status, 'id' => '123123', 'purchase_units' => [['reference_id' => 'REFERENCE_ID']]];

        $this->orderDetailsApi->expects(self::once())->method('get')->with($this->paymentMethod, '123123')->willReturn($notApproved);
        $this->approvedPayPalOrderUpdater->expects(self::never())->method('update');
        $this->completeOrderApi->expects(self::never())->method('complete');
        $this->payment->expects(self::never())->method('setDetails');

        self::assertSame($notApproved, $this->processor->capture($this->payment));
    }

    /** @return iterable<string, array{string, string}> */
    public static function ordersThePayerHasNotApproved(): iterable
    {
        yield 'created, left in the PayPal window' => ['paypal', 'CREATED'];
        yield 'created, left in the Venmo window' => ['venmo', 'CREATED'];
        yield 'payer action required' => ['card', 'PAYER_ACTION_REQUIRED'];
        yield 'voided' => ['card', 'VOIDED'];
    }

    public function test_it_captures_a_card_order_paypal_leaves_created_after_3d_secure(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID', 'payment_source' => 'card'], amount: 1000, total: 1000);

        $this->orderDetailsApi->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls(['status' => 'CREATED'] + self::APPROVED_ORDER, self::ORDER_DETAILS);
        $this->completeOrderApi->expects(self::once())->method('complete')->with($this->paymentMethod, '123123');

        self::assertSame(self::ORDER_DETAILS, $this->processor->capture($this->payment));
    }

    public function test_it_records_an_order_paypal_has_already_captured_without_capturing_it_again(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1000);

        $this->orderDetailsApi->expects(self::once())->method('get')->willReturn(self::ORDER_DETAILS);
        $this->completeOrderApi->expects(self::never())->method('complete');
        $this->payment->expects(self::once())->method('setDetails')->with(self::callback(
            fn (array $details): bool => 'TRANSACTION_ID' === $details['transaction_id'],
        ));

        self::assertSame(self::ORDER_DETAILS, $this->processor->capture($this->payment));
    }

    public function test_it_only_reads_an_order_paypal_captures_on_payment_approval(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'payment_source' => 'trustly'], amount: 1000, total: 1200);

        $this->approvedPayPalOrderUpdater->expects(self::never())->method('update');
        $this->completeOrderApi->expects(self::never())->method('complete');
        $this->orderDetailsApi->expects(self::once())->method('get')->with($this->paymentMethod, '123123')->willReturn(self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails')->with(self::callback(
            fn (array $details): bool => 'trustly' === $details['payment_source'] && 'TRANSACTION_ID' === $details['transaction_id'],
        ));

        self::assertSame(self::ORDER_DETAILS, $this->processor->capture($this->payment));
    }

    public function test_it_carries_the_payment_source_and_the_transaction_id_through_the_capture(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'payment_source' => 'google_pay'], amount: 1000, total: 1000);

        $this->paypalReadsTheApprovedOrderThen(self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => PayPalPaymentStatus::Completed->value,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'google_pay',
            'transaction_id' => 'TRANSACTION_ID',
        ]);

        $this->processor->capture($this->payment);
    }

    public function test_it_keeps_the_paypal_orders_earlier_attempts_superseded_through_the_capture(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'payment_source' => 'google_pay', 'superseded_paypal_orders' => ['TRUSTLY_ORDER_ID' => 'trustly']], amount: 1000, total: 1000);

        $this->paypalReadsTheApprovedOrderThen(self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails')->with(self::callback(
            static fn (array $details): bool => ['TRUSTLY_ORDER_ID' => 'trustly'] === $details['superseded_paypal_orders'],
        ));

        $this->processor->capture($this->payment);
    }

    public function test_it_brings_the_approved_paypal_order_up_to_date_before_capturing_it(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123'], amount: 1000, total: 1200);

        $this->approvedPayPalOrderUpdater->expects(self::once())->method('update')->with($this->payment);
        $this->completeOrderApi->expects(self::once())->method('complete');
        $this->paypalReadsTheApprovedOrderThen(self::ORDER_DETAILS);

        $this->processor->capture($this->payment);
    }

    public function test_it_reads_the_order_paypal_could_not_answer_the_capture_of(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1000);

        $this->completeOrderApi->method('complete')->willThrowException(new PayPalApiServerException('POST', 'v2/checkout/orders/123123/capture', 503));
        $this->paypalReadsTheApprovedOrderThen(self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails');

        self::assertSame(self::ORDER_DETAILS, $this->processor->capture($this->payment));
    }

    #[DataProvider('issuesLeavingTheOrderToRead')]
    public function test_it_reads_the_order_paypal_refused_to_capture_for_its_state(string $issue): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1000);
        $notCaptured = ['status' => 'CREATED', 'id' => '123123', 'purchase_units' => [['reference_id' => 'REFERENCE_ID']]];

        $this->completeOrderApi->method('complete')->willThrowException(new PayPalApiRequestException('POST', 'v2/checkout/orders/123123/capture', 422, 'UNPROCESSABLE_ENTITY', issues: [$issue]));
        $this->paypalReadsTheApprovedOrderThen($notCaptured);

        self::assertSame($notCaptured, $this->processor->capture($this->payment));
    }

    /** @return iterable<string, array{string}> */
    public static function issuesLeavingTheOrderToRead(): iterable
    {
        yield 'already captured' => ['ORDER_ALREADY_CAPTURED'];
        yield 'not approved' => ['ORDER_NOT_APPROVED'];
        yield 'payer action required' => ['PAYER_ACTION_REQUIRED'];
    }

    public function test_it_lets_a_capture_paypal_refused_for_the_payer_through(): void
    {
        $this->paymentOf(['paypal_order_id' => '123123', 'reference_id' => 'REFERENCE_ID'], amount: 1000, total: 1000);
        $declined = new PayPalApiRequestException('POST', 'v2/checkout/orders/123123/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['INSTRUMENT_DECLINED']);

        $this->completeOrderApi->method('complete')->willThrowException($declined);
        $this->orderDetailsApi->expects(self::once())->method('get')->willReturn(self::APPROVED_ORDER);

        $this->expectExceptionObject($declined);

        $this->processor->capture($this->payment);
    }

    /** @param array<string, mixed> $order */
    private function paypalReadsTheApprovedOrderThen(array $order): void
    {
        $this->orderDetailsApi->expects(self::exactly(2))->method('get')->with($this->paymentMethod, '123123')->willReturnOnConsecutiveCalls(self::APPROVED_ORDER, $order);
    }

    /** @param array<string, mixed> $details */
    private function paymentOf(array $details, int $amount, int $total): void
    {
        $this->payment->method('getDetails')->willReturn($details);
        $this->payment->method('getAmount')->willReturn($amount);
        $this->order->method('getTotal')->willReturn($total);
    }
}
