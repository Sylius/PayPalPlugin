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
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
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
            ->withPayerAction('https://www.paypal.com/payer-action')
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

    public function test_it_knows_the_paypal_orders_it_has_superseded(): void
    {
        $details = PayPalPaymentDetails::fromArray([
            'paypal_order_id' => 'CURRENT',
            'superseded_paypal_orders' => ['SUPERSEDED' => 'trustly', 'BROKEN' => 13],
        ]);

        self::assertSame(['SUPERSEDED' => 'trustly'], $details->supersededPayPalOrders());
        self::assertTrue($details->isSupersededPayPalOrder('SUPERSEDED'));
        self::assertFalse($details->isSupersededPayPalOrder('CURRENT'));
        self::assertTrue($details->isPayPalOrder('CURRENT'));
        self::assertTrue($details->isPayPalOrder('SUPERSEDED'));
        self::assertFalse($details->isPayPalOrder('UNKNOWN'));
    }

    public function test_it_supersedes_the_current_paypal_order_along_with_the_earlier_ones(): void
    {
        self::assertSame(
            ['SUPERSEDED' => 'card', 'CURRENT' => 'trustly'],
            PayPalPaymentDetails::fromArray([
                'paypal_order_id' => 'CURRENT',
                'payment_source' => 'trustly',
                'superseded_paypal_orders' => ['SUPERSEDED' => 'card'],
            ])->payPalOrdersToSupersede(),
        );
        self::assertSame([], PayPalPaymentDetails::create()->payPalOrdersToSupersede());
    }

    public function test_it_writes_no_superseded_paypal_orders_when_there_are_none(): void
    {
        self::assertSame([], PayPalPaymentDetails::fromArray(['superseded_paypal_orders' => ['SUPERSEDED' => 'card']])->withSupersededPayPalOrders([])->toArray());
    }

    public function test_it_restores_a_superseded_paypal_order_as_the_current_one(): void
    {
        $details = PayPalPaymentDetails::fromArray([
            'status' => 'CAPTURED',
            'paypal_order_id' => 'CURRENT',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'paypal',
            'transaction_id' => 'CAPTURE_ID',
            'paypal_order_intent' => 'AUTHORIZE',
            'superseded_paypal_orders' => ['SUPERSEDED' => 'trustly'],
        ])->withSupersededPayPalOrderRestored('SUPERSEDED');

        self::assertSame([
            'status' => 'CAPTURED',
            'paypal_order_id' => 'SUPERSEDED',
            'payment_source' => 'trustly',
            'superseded_paypal_orders' => ['CURRENT' => 'paypal'],
        ], $details->toArray());
    }

    public function test_it_knows_the_refund_paypal_has_confirmed(): void
    {
        $details = PayPalPaymentDetails::fromArray(['paypal_order_id' => 'PAYPAL_ORDER_ID'])->withRefund('REFUND_ID');

        self::assertTrue($details->isRefunded());
        self::assertSame('REFUND_ID', $details->refundId());
        self::assertSame([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'refunded' => true,
            'refund_id' => 'REFUND_ID',
        ], $details->toArray());
    }

    public function test_it_knows_a_refund_confirmed_without_its_id(): void
    {
        $details = PayPalPaymentDetails::create()->withRefund(null);

        self::assertTrue($details->isRefunded());
        self::assertNull($details->refundId());
    }

    public function test_it_knows_no_refund_by_default(): void
    {
        $details = PayPalPaymentDetails::fromArray(['refunded' => 'yes']);

        self::assertFalse($details->isRefunded());
        self::assertNull($details->refundId());
    }

    public function test_it_knows_the_authorization_paypal_holds_for_the_payment(): void
    {
        $details = PayPalPaymentDetails::fromArray(['paypal_order_id' => 'PAYPAL_ORDER_ID'])
            ->withAuthorization(new PayPalAuthorization('CREATED', 'AUTHORIZATION_ID'))
        ;

        self::assertSame('AUTHORIZATION_ID', $details->authorizationId());
        self::assertSame('CREATED', $details->authorizationStatus());
        self::assertSame([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'authorization_id' => 'AUTHORIZATION_ID',
            'authorization_status' => 'CREATED',
        ], $details->toArray());
    }

    public function test_it_keeps_the_authorization_id_when_an_update_carries_only_a_status(): void
    {
        $details = PayPalPaymentDetails::create()
            ->withAuthorization(new PayPalAuthorization('CREATED', 'AUTHORIZATION_ID'))
            ->withAuthorization(new PayPalAuthorization('VOIDED'))
        ;

        self::assertSame('AUTHORIZATION_ID', $details->authorizationId());
        self::assertSame('VOIDED', $details->authorizationStatus());
    }

    public function test_it_knows_no_authorization_by_default(): void
    {
        $details = PayPalPaymentDetails::create();

        self::assertNull($details->authorizationId());
        self::assertNull($details->authorizationStatus());
    }

    public function test_it_knows_the_paypal_order_authorizes_when_created_with_the_authorize_intent(): void
    {
        $details = PayPalPaymentDetails::create()->withPayPalOrderIntent('AUTHORIZE');

        self::assertTrue($details->isAuthorizeIntent());
        self::assertSame(['paypal_order_intent' => 'AUTHORIZE'], $details->toArray());
    }

    public function test_it_knows_the_paypal_order_captures_when_created_with_the_capture_intent(): void
    {
        self::assertFalse(PayPalPaymentDetails::create()->withPayPalOrderIntent('CAPTURE')->isAuthorizeIntent());
    }

    public function test_it_knows_the_paypal_order_captures_when_no_intent_is_stored(): void
    {
        self::assertFalse(PayPalPaymentDetails::create()->isAuthorizeIntent());
    }

    public function test_it_reads_the_status_of_an_authorized_payment(): void
    {
        self::assertSame(PayPalPaymentStatus::Authorized, PayPalPaymentDetails::fromArray(['status' => 'AUTHORIZED'])->status());
    }

    public function test_it_leaves_the_original_untouched_when_writing(): void
    {
        $details = PayPalPaymentDetails::create();
        $details->withPayPalOrderId('PAYPAL_ORDER_ID');

        self::assertSame([], $details->toArray());
    }
}
