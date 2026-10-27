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
    public function test_it_reads_the_last_completed_refund_of_the_first_purchase_unit(): void
    {
        $refund = PayPalRefund::completedFromPayPalOrder($this->payPalOrder([
            ['id' => 'FIRST_REFUND_ID', 'status' => 'COMPLETED'],
            ['id' => 'LAST_REFUND_ID', 'status' => 'COMPLETED'],
            ['id' => 'FAILED_REFUND_ID', 'status' => 'FAILED'],
        ]));

        self::assertSame('LAST_REFUND_ID', $refund?->id());
        self::assertSame('COMPLETED', $refund?->status());
    }

    public function test_it_reads_no_refund_from_an_order_paypal_has_not_refunded(): void
    {
        self::assertNull(PayPalRefund::completedFromPayPalOrder([]));
        self::assertNull(PayPalRefund::completedFromPayPalOrder(['purchase_units' => [['payments' => ['captures' => []]]]]));
        self::assertNull(PayPalRefund::completedFromPayPalOrder($this->payPalOrder([['id' => 'REFUND_ID', 'status' => 'PENDING'], ['status' => 'COMPLETED']])));
    }

    /**
     * @param list<array<string, mixed>> $refunds
     *
     * @return array<string, mixed>
     */
    private function payPalOrder(array $refunds): array
    {
        return ['purchase_units' => [['payments' => ['refunds' => $refunds]]]];
    }
}
