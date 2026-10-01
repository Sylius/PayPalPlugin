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

namespace Sylius\PayPalPlugin;

final class AmountUtils
{
    public const CURRENCIES_WITHOUT_DECIMALS = ['HUF', 'JPY', 'TWD'];

    private function __construct()
    {
    }

    public static function toPayPalValue(int $amount, string $currencyCode): string
    {
        return number_format($amount / 100, self::decimals($currencyCode), '.', '');
    }

    public static function toMinorUnits(string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private static function decimals(string $currencyCode): int
    {
        return in_array(strtoupper($currencyCode), self::CURRENCIES_WITHOUT_DECIMALS, true) ? 0 : 2;
    }
}
