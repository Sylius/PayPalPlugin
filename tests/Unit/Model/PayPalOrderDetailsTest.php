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
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\PayPalRefund;

final class PayPalOrderDetailsTest extends TestCase
{
    public function test_it_finds_the_last_completed_refund_of_a_capture(): void
    {
        $details = $this->orderDetails([
            new PayPalRefund('FIRST_REFUND_ID', 'COMPLETED', 'CAPTURE_ID'),
            new PayPalRefund('LAST_REFUND_ID', 'COMPLETED', 'CAPTURE_ID'),
            new PayPalRefund('PENDING_REFUND_ID', 'PENDING', 'CAPTURE_ID'),
            new PayPalRefund('OTHER_REFUND_ID', 'COMPLETED', 'OTHER_CAPTURE_ID'),
        ]);

        self::assertSame('LAST_REFUND_ID', $details->completedRefundOf('CAPTURE_ID')?->id());
        self::assertSame('OTHER_REFUND_ID', $details->completedRefundOf('OTHER_CAPTURE_ID')?->id());
    }

    public function test_it_finds_no_refund_of_a_capture_paypal_lists_no_completed_refund_of(): void
    {
        $details = $this->orderDetails([
            new PayPalRefund('PENDING_REFUND_ID', 'PENDING', 'CAPTURE_ID'),
            new PayPalRefund('OTHER_REFUND_ID', 'COMPLETED', 'OTHER_CAPTURE_ID'),
            new PayPalRefund('UNLINKED_REFUND_ID', 'COMPLETED'),
        ]);

        self::assertNull($details->completedRefundOf('CAPTURE_ID'));
        self::assertNull($this->orderDetails([])->completedRefundOf('CAPTURE_ID'));
    }

    /** @param list<PayPalRefund> $refunds */
    private function orderDetails(array $refunds): PayPalOrderDetails
    {
        return new PayPalOrderDetails('PAYPAL_ORDER_ID', refunds: $refunds);
    }
}
