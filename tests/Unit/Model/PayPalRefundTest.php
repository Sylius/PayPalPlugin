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
use Sylius\PayPalPlugin\Model\PayPalRefund;

final class PayPalRefundTest extends TestCase
{
    public function test_it_reads_the_refund_and_the_capture_it_refunds(): void
    {
        $refund = PayPalRefund::fromRefund([
            'id' => 'REFUND_ID',
            'status' => 'COMPLETED',
            'links' => [
                ['rel' => 'self', 'href' => 'https://api.sandbox.paypal.com/v2/payments/refunds/REFUND_ID'],
                ['rel' => 'up', 'href' => 'https://api.sandbox.paypal.com/v2/payments/captures/CAPTURE_ID'],
            ],
        ]);

        self::assertSame('REFUND_ID', $refund?->id());
        self::assertSame('COMPLETED', $refund?->status());
        self::assertTrue($refund?->isCompleted());
        self::assertSame('CAPTURE_ID', $refund?->captureId());
    }

    public function test_it_reads_no_capture_without_a_link_up_to_it(): void
    {
        self::assertNull(PayPalRefund::fromRefund(['id' => 'REFUND_ID', 'status' => 'PENDING'])?->captureId());
        self::assertNull(PayPalRefund::fromRefund([
            'id' => 'REFUND_ID',
            'status' => 'PENDING',
            'links' => [['rel' => 'up', 'href' => 'https://api.sandbox.paypal.com/v2/checkout/orders/ORDER_ID']],
        ])?->captureId());
    }

    public function test_it_reads_no_refund_without_its_id_or_status(): void
    {
        self::assertNull(PayPalRefund::fromRefund([]));
        self::assertNull(PayPalRefund::fromRefund(['status' => 'COMPLETED']));
        self::assertNull(PayPalRefund::fromRefund(['id' => 'REFUND_ID']));
    }

    public function test_it_is_completed_only_once_paypal_completes_it(): void
    {
        self::assertFalse((new PayPalRefund('REFUND_ID', 'PENDING'))->isCompleted());
    }
}
