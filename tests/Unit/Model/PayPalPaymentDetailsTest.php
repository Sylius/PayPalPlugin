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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;

final class PayPalPaymentDetailsTest extends TestCase
{
    public function test_it_reads_the_details_a_captured_payment_carries(): void
    {
        $details = PayPalPaymentDetails::fromArray([
            'status' => 'CAPTURED',
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'reference_id' => 'REFERENCE_ID',
            'payment_amount' => 1000,
            'payment_source' => 'trustly',
            'transaction_id' => 'CAPTURE_ID',
            'payer_action_url' => 'https://www.paypal.com/payer-action',
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
        ]);

        self::assertSame(PayPalPaymentStatus::Captured, $details->status());
        self::assertTrue($details->isStatus(PayPalPaymentStatus::Captured));
        self::assertSame('PAYPAL_ORDER_ID', $details->payPalOrderId());
        self::assertTrue($details->hasPayPalOrderId());
        self::assertSame('REFERENCE_ID', $details->referenceId());
        self::assertSame(1000, $details->amount());
        self::assertSame('trustly', $details->paymentSource());
        self::assertSame('CAPTURE_ID', $details->transactionId());
        self::assertSame('https://www.paypal.com/payer-action', $details->payerActionUrl());
        self::assertSame('RETURN_NONCE', $details->payerActionReturnNonce());
        self::assertSame('CANCEL_NONCE', $details->payerActionCancelNonce());
    }

    public function test_it_reads_nothing_from_empty_details(): void
    {
        $details = PayPalPaymentDetails::create();

        self::assertNull($details->status());
        self::assertNull($details->payPalOrderId());
        self::assertFalse($details->hasPayPalOrderId());
        self::assertNull($details->referenceId());
        self::assertSame(0, $details->amount());
        self::assertSame('paypal', $details->paymentSource());
        self::assertNull($details->transactionId());
        self::assertNull($details->payerActionUrl());
        self::assertSame([], $details->toArray());
    }

    public function test_it_does_not_treat_an_empty_paypal_order_id_as_present(): void
    {
        self::assertFalse(PayPalPaymentDetails::fromArray(['paypal_order_id' => ''])->hasPayPalOrderId());
    }

    public function test_it_ignores_a_status_it_does_not_know(): void
    {
        self::assertNull(PayPalPaymentDetails::fromArray(['status' => 'UNKNOWN'])->status());
    }

    public function test_it_reads_the_details_off_a_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        self::assertSame('PAYPAL_ORDER_ID', PayPalPaymentDetails::fromPayment($payment)->payPalOrderId());
    }

    public function test_it_writes_the_details_in_their_stored_shape(): void
    {
        $details = PayPalPaymentDetails::create()
            ->withStatus(PayPalPaymentStatus::Completed)
            ->withPayPalOrderId('PAYPAL_ORDER_ID')
            ->withReferenceId('REFERENCE_ID')
            ->withAmount(1000)
            ->withPaymentSource('paypal')
            ->withTransactionId('CAPTURE_ID')
            ->withPayerAction('https://www.paypal.com/payer-action', 'RETURN_NONCE', 'CANCEL_NONCE')
            ->withCapturedAmount(900, 'USD')
        ;

        self::assertSame([
            'status' => 'COMPLETED',
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'reference_id' => 'REFERENCE_ID',
            'payment_amount' => 1000,
            'payment_source' => 'paypal',
            'transaction_id' => 'CAPTURE_ID',
            'payer_action_url' => 'https://www.paypal.com/payer-action',
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
            'captured_amount' => 900,
            'captured_currency_code' => 'USD',
        ], $details->toArray());
    }

    public function test_it_keeps_keys_it_does_not_know_when_writing(): void
    {
        $details = PayPalPaymentDetails::fromArray(['custom' => 'value'])->withStatus(PayPalPaymentStatus::Processing);

        self::assertSame(['custom' => 'value', 'status' => 'PROCESSING'], $details->toArray());
    }

    public function test_it_clears_only_the_payer_action(): void
    {
        $details = PayPalPaymentDetails::fromArray([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'payer_action_url' => 'https://www.paypal.com/payer-action',
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
        ])->clearPayerAction();

        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID'], $details->toArray());
    }

    public function test_it_leaves_the_original_untouched_when_writing(): void
    {
        $details = PayPalPaymentDetails::create();
        $details->withPayPalOrderId('PAYPAL_ORDER_ID');

        self::assertSame([], $details->toArray());
    }
}
