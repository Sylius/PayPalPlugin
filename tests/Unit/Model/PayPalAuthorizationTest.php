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
use Sylius\PayPalPlugin\Model\PayPalAuthorization;

final class PayPalAuthorizationTest extends TestCase
{
    public function test_it_reads_the_first_authorization_of_the_first_purchase_unit(): void
    {
        $authorization = PayPalAuthorization::fromPayPalOrder($this->payPalOrder([
            'id' => '0VF52814937998046',
            'status' => 'CREATED',
            'amount' => ['currency_code' => 'EUR', 'value' => '15.39'],
        ]));

        self::assertSame('0VF52814937998046', $authorization?->id());
        self::assertSame('CREATED', $authorization?->status());
        self::assertTrue($authorization?->isStatus(PayPalAuthorization::STATUS_CREATED));
        self::assertFalse($authorization?->isStatus(PayPalAuthorization::STATUS_VOIDED));
    }

    public function test_it_reads_no_authorization_from_an_order_paypal_has_not_authorized_yet(): void
    {
        self::assertNull(PayPalAuthorization::fromPayPalOrder([]));
        self::assertNull(PayPalAuthorization::fromPayPalOrder(['purchase_units' => [[]]]));
        self::assertNull(PayPalAuthorization::fromPayPalOrder(['purchase_units' => [['payments' => ['authorizations' => []]]]]));
        self::assertNull(PayPalAuthorization::fromPayPalOrder(['purchase_units' => [['payments' => ['captures' => [['status' => 'COMPLETED']]]]]]));
    }

    public function test_it_reads_no_authorization_without_a_status(): void
    {
        self::assertNull(PayPalAuthorization::fromPayPalOrder($this->payPalOrder(['id' => 'AUTHORIZATION_ID'])));
    }

    public function test_it_reads_an_authorization_that_carries_no_id(): void
    {
        $authorization = PayPalAuthorization::fromPayPalOrder($this->payPalOrder(['status' => 'PENDING']));

        self::assertSame('PENDING', $authorization?->status());
        self::assertNull($authorization?->id());
    }

    public function test_it_reads_an_authorization_paypal_returns_on_its_own(): void
    {
        $authorization = PayPalAuthorization::fromAuthorization(['id' => 'AUTHORIZATION_ID', 'status' => 'VOIDED']);

        self::assertSame('AUTHORIZATION_ID', $authorization?->id());
        self::assertSame('VOIDED', $authorization?->status());
        self::assertNull(PayPalAuthorization::fromAuthorization([]));
    }

    /**
     * @param array<string, mixed> $authorization
     *
     * @return array<string, mixed>
     */
    private function payPalOrder(array $authorization): array
    {
        return ['purchase_units' => [['payments' => ['authorizations' => [$authorization]]]]];
    }
}
