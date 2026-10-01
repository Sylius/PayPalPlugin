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

namespace Tests\Sylius\PayPalPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\AmountUtils;

final class AmountUtilsTest extends TestCase
{
    public function test_it_formats_an_amount_with_the_two_decimals_most_currencies_take(): void
    {
        self::assertSame('10.00', AmountUtils::toPayPalValue(1000, 'USD'));
        self::assertSame('17.99', AmountUtils::toPayPalValue(1799, 'EUR'));
        self::assertSame('0.05', AmountUtils::toPayPalValue(5, 'GBP'));
    }

    #[DataProvider('currenciesWithoutDecimals')]
    public function test_it_formats_an_amount_of_a_currency_paypal_refuses_decimals_for(string $currencyCode): void
    {
        self::assertSame('1000', AmountUtils::toPayPalValue(100000, $currencyCode));
        self::assertSame('1000', AmountUtils::toPayPalValue(100000, strtolower($currencyCode)));
    }

    public function test_it_reads_an_amount_paypal_sent_back(): void
    {
        self::assertSame(1000, AmountUtils::toMinorUnits('10.00'));
        self::assertSame(1799, AmountUtils::toMinorUnits('17.99'));
        self::assertSame(100000, AmountUtils::toMinorUnits('1000'));
    }

    public function test_it_reads_an_amount_back_without_losing_a_cent_to_the_float(): void
    {
        self::assertSame(29, AmountUtils::toMinorUnits('0.29'));
        self::assertSame(115, AmountUtils::toMinorUnits('1.15'));
    }

    #[DataProvider('roundTripAmounts')]
    public function test_it_returns_the_same_amount_it_was_given(int $amount, string $currencyCode): void
    {
        self::assertSame($amount, AmountUtils::toMinorUnits(AmountUtils::toPayPalValue($amount, $currencyCode)));
    }

    /** @return iterable<array{string}> */
    public static function currenciesWithoutDecimals(): iterable
    {
        yield ['HUF'];
        yield ['JPY'];
        yield ['TWD'];
    }

    /** @return iterable<array{int, string}> */
    public static function roundTripAmounts(): iterable
    {
        yield [1799, 'USD'];
        yield [29, 'EUR'];
        yield [100000, 'JPY'];
        yield [100, 'HUF'];
    }
}
