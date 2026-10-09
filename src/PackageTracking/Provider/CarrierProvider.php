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

namespace Sylius\PayPalPlugin\PackageTracking\Provider;

final readonly class CarrierProvider implements CarrierProviderInterface
{
    /** @var array<string> */
    private array $carrierCodes;

    /** @param array<string> $carrierCodes */
    public function __construct(array $carrierCodes)
    {
        $codes = array_values(array_unique($carrierCodes));
        if (!in_array(self::OTHER_CARRIER_CODE, $codes, true)) {
            $codes[] = self::OTHER_CARRIER_CODE;
        }

        $this->carrierCodes = $codes;
    }

    public function getCarrierCodes(): array
    {
        return $this->carrierCodes;
    }

    public function isOther(string $code): bool
    {
        return self::OTHER_CARRIER_CODE === $code;
    }
}
