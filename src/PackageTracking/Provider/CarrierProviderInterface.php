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

interface CarrierProviderInterface
{
    public const OTHER_CARRIER_CODE = 'OTHER';

    /**
     * @return array<string>
     */
    public function getCarrierCodes(): array;

    public function isOther(string $code): bool;
}
