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

final readonly class PayPalShipping
{
    public function __construct(
        private ?string $fullName = null,
        private ?PayPalAddress $address = null,
        private ?string $selectedOptionId = null,
        private ?string $phoneNumber = null,
    ) {
    }

    public function fullName(): ?string
    {
        return $this->fullName;
    }

    public function address(): ?PayPalAddress
    {
        return $this->address;
    }

    public function selectedOptionId(): ?string
    {
        return $this->selectedOptionId;
    }

    public function phoneNumber(): ?string
    {
        return $this->phoneNumber;
    }
}
