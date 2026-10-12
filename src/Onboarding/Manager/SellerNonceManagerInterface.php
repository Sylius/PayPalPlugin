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

namespace Sylius\PayPalPlugin\Onboarding\Manager;

interface SellerNonceManagerInterface
{
    public function generate(bool $sandbox = false): string;

    public function get(): ?string;

    public function isSandbox(): bool;

    public function remove(): void;
}
