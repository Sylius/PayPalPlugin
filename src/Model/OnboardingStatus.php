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

final readonly class OnboardingStatus
{
    public const VAULTING_CAPABILITY = 'PAYPAL_WALLET_VAULTING_ADVANCED';

    public const ACTIVE_CAPABILITY_STATUS = 'ACTIVE';

    public function __construct(
        private bool $paymentsReceivable,
        private bool $primaryEmailConfirmed,
        private ?string $vaultingCapabilityStatus = null,
    ) {
    }

    public function arePaymentsReceivable(): bool
    {
        return $this->paymentsReceivable;
    }

    public function isPrimaryEmailConfirmed(): bool
    {
        return $this->primaryEmailConfirmed;
    }

    public function isComplete(): bool
    {
        return $this->paymentsReceivable && $this->primaryEmailConfirmed;
    }

    public function getVaultingCapabilityStatus(): ?string
    {
        return $this->vaultingCapabilityStatus;
    }

    public function isVaultingEnabled(): bool
    {
        return self::ACTIVE_CAPABILITY_STATUS === $this->vaultingCapabilityStatus;
    }
}
