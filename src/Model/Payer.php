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

final readonly class Payer
{
    public function __construct(
        private ?string $email = null,
        private ?string $givenName = null,
        private ?string $surname = null,
        private ?string $countryCode = null,
        private ?string $phoneNumber = null,
    ) {
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function givenName(): ?string
    {
        return $this->givenName;
    }

    public function surname(): ?string
    {
        return $this->surname;
    }

    public function countryCode(): ?string
    {
        return $this->countryCode;
    }

    public function phoneNumber(): ?string
    {
        return $this->phoneNumber;
    }
}
