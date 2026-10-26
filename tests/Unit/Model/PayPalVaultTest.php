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
use Sylius\PayPalPlugin\Model\PayPalVault;

final class PayPalVaultTest extends TestCase
{
    public function test_it_reads_the_vault_paypal_saved_the_wallet_in(): void
    {
        $vault = PayPalVault::fromPayPalOrder([
            'payment_source' => ['paypal' => [
                'email_address' => 'buyer@example.com',
                'attributes' => ['vault' => [
                    'id' => 'j7hzkgdn',
                    'status' => 'VAULTED',
                    'customer' => ['id' => '163456532'],
                ]],
            ]],
        ]);

        self::assertSame('j7hzkgdn', $vault->id());
        self::assertSame('VAULTED', $vault->status());
        self::assertSame('163456532', $vault->customerId());
        self::assertSame('buyer@example.com', $vault->payerEmail());
    }

    public function test_it_reads_a_vault_paypal_approved_but_has_not_saved_yet(): void
    {
        $vault = PayPalVault::fromPayPalOrder([
            'payment_source' => ['paypal' => ['attributes' => ['vault' => [
                'status' => 'APPROVED',
                'customer' => ['id' => '163456532'],
            ]]]],
        ]);

        self::assertNull($vault?->id());
        self::assertSame('APPROVED', $vault->status());
        self::assertSame('163456532', $vault->customerId());
    }

    public function test_it_reads_no_vault_from_an_order_paying_without_saving_the_wallet(): void
    {
        self::assertNull(PayPalVault::fromPayPalOrder([]));
        self::assertNull(PayPalVault::fromPayPalOrder(['payment_source' => ['card' => ['attributes' => ['vault' => ['status' => 'VAULTED']]]]]));
        self::assertNull(PayPalVault::fromPayPalOrder(['payment_source' => ['paypal' => ['email_address' => 'buyer@example.com']]]));
    }

    public function test_it_reads_no_vault_without_a_status(): void
    {
        self::assertNull(PayPalVault::fromPayPalOrder(['payment_source' => ['paypal' => ['attributes' => ['vault' => ['id' => 'j7hzkgdn']]]]]));
    }
}
