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
    /** @param list<string> $activeCapabilities */
    public function __construct(
        private bool $paymentsReceivable,
        private bool $primaryEmailConfirmed,
        private array $activeCapabilities = [],
    ) {
    }

    /** @param array{payments_receivable?: bool, primary_email_confirmed?: bool, active_capabilities?: list<string>} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['payments_receivable'] ?? false,
            $data['primary_email_confirmed'] ?? false,
            $data['active_capabilities'] ?? [],
        );
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

    /** @return list<string> */
    public function activeCapabilities(): array
    {
        return $this->activeCapabilities;
    }

    public function hasActiveCapability(string $capability): bool
    {
        return in_array($capability, $this->activeCapabilities, true);
    }

    /** @return array{payments_receivable: bool, primary_email_confirmed: bool, active_capabilities: list<string>} */
    public function toArray(): array
    {
        return [
            'payments_receivable' => $this->paymentsReceivable,
            'primary_email_confirmed' => $this->primaryEmailConfirmed,
            'active_capabilities' => $this->activeCapabilities,
        ];
    }
}
