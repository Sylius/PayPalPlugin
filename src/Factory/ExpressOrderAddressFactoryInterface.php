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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;

interface ExpressOrderAddressFactoryInterface
{
    /** @param array<string, mixed> $purchaseUnit */
    public function createFromPurchaseUnit(array $purchaseUnit, ?string $phoneNumber): AddressInterface;

    public function createFromCustomer(
        CustomerInterface $customer,
        ?string $countryCode,
        ?string $phoneNumber,
    ): AddressInterface;
}
