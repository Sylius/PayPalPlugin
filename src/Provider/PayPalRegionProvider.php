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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\AddressInterface;

final class PayPalRegionProvider
{
    public static function provide(AddressInterface $address): ?string
    {
        $provinceCode = trim((string) $address->getProvinceCode());
        if ('' !== $provinceCode) {
            $prefix = $address->getCountryCode() . '-';

            return str_starts_with($provinceCode, $prefix) ? substr($provinceCode, strlen($prefix)) : $provinceCode;
        }

        $provinceName = trim((string) $address->getProvinceName());

        return '' !== $provinceName ? $provinceName : null;
    }
}
