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

final readonly class PayPalAddress
{
    public function __construct(
        private ?string $addressLine1 = null,
        private ?string $adminArea1 = null,
        private ?string $adminArea2 = null,
        private ?string $postalCode = null,
        private ?string $countryCode = null,
    ) {
    }

    public function addressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function adminArea1(): ?string
    {
        return $this->adminArea1;
    }

    public function adminArea2(): ?string
    {
        return $this->adminArea2;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function countryCode(): ?string
    {
        return $this->countryCode;
    }
}
