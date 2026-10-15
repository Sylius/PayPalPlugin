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

final readonly class AccessToken
{
    public function __construct(
        private string $value,
        private int $expiresIn,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }

    public function expiresIn(): int
    {
        return $this->expiresIn;
    }
}
