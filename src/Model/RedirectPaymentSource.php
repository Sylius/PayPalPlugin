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

namespace Sylius\PayPalPlugin\Model;

enum RedirectPaymentSource: string
{
    case Trustly = 'trustly';

    public function eligibilityCode(): string
    {
        return match ($this) {
            self::Trustly => 'TRUSTLY',
        };
    }

    public function iconUrl(): string
    {
        return sprintf(
            'https://www.paypalobjects.com/images/checkout/alternative_payments/paypal_%s_color.svg',
            $this->iconCode(),
        );
    }

    public function configurationKey(): string
    {
        return $this->value . '_enabled';
    }

    private function iconCode(): string
    {
        return match ($this) {
            self::Trustly => 'trustly',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
