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

final readonly class PayPalVaultRequirement
{
    private function __construct(
        private bool $met,
        private ?PayPalVaultRequest $vaultRequest,
    ) {
    }

    public static function notRequired(): self
    {
        return new self(true, null);
    }

    public static function met(PayPalVaultRequest $vaultRequest): self
    {
        return new self(true, $vaultRequest);
    }

    public static function unmet(): self
    {
        return new self(false, null);
    }

    public function isMet(): bool
    {
        return $this->met;
    }

    public function vaultRequest(): ?PayPalVaultRequest
    {
        return $this->vaultRequest;
    }
}
