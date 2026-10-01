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
use Sylius\PayPalPlugin\Model\PayPalCapture;

final class PayPalCaptureTest extends TestCase
{
    public function test_it_reads_the_first_capture_of_the_first_purchase_unit(): void
    {
        $capture = PayPalCapture::fromPayPalOrder($this->payPalOrder([
            'id' => '892032536L382192T',
            'status' => 'COMPLETED',
            'amount' => ['currency_code' => 'EUR', 'value' => '15.39'],
        ]));

        self::assertSame('892032536L382192T', $capture?->id());
        self::assertSame('COMPLETED', $capture?->status());
        self::assertSame(1539, $capture?->amount());
        self::assertSame('EUR', $capture?->currencyCode());
    }

    public function test_it_reads_no_capture_from_an_order_paypal_has_not_captured_yet(): void
    {
        self::assertNull(PayPalCapture::fromPayPalOrder([]));
        self::assertNull(PayPalCapture::fromPayPalOrder(['purchase_units' => [[]]]));
        self::assertNull(PayPalCapture::fromPayPalOrder(['purchase_units' => [['payments' => ['captures' => []]]]]));
    }

    public function test_it_reads_no_capture_without_a_status_to_settle_on(): void
    {
        self::assertNull(PayPalCapture::fromPayPalOrder($this->payPalOrder(['id' => 'CAPTURE_ID'])));
    }

    public function test_it_reads_a_capture_that_carries_no_amount(): void
    {
        $capture = PayPalCapture::fromPayPalOrder($this->payPalOrder(['status' => 'PENDING']));

        self::assertSame('PENDING', $capture?->status());
        self::assertNull($capture?->id());
        self::assertNull($capture?->amount());
        self::assertNull($capture?->currencyCode());
    }

    public function test_it_rounds_the_amount_to_minor_units_rather_than_truncating_it(): void
    {
        foreach (['0.01' => 1, '15.39' => 1539, '100.59' => 10059, '1.005' => 101] as $value => $expected) {
            $capture = PayPalCapture::fromPayPalOrder($this->payPalOrder([
                'status' => 'COMPLETED',
                'amount' => ['currency_code' => 'EUR', 'value' => (string) $value],
            ]));

            self::assertSame($expected, $capture?->amount(), (string) $value);
        }
    }

    /**
     * @param array<string, mixed> $capture
     *
     * @return array<string, mixed>
     */
    private function payPalOrder(array $capture): array
    {
        return ['purchase_units' => [['payments' => ['captures' => [$capture]]]]];
    }
}
