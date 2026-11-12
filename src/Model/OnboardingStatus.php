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

    /** @param list<string> $activeCapabilities */
    public function __construct(
        private bool $paymentsReceivable,
        private bool $primaryEmailConfirmed,
        private array $activeCapabilities = [],
        private ?string $vaultingCapabilityStatus = null,
    ) {
    }

    /** @param array{payments_receivable?: bool, primary_email_confirmed?: bool, active_capabilities?: list<string>, vaulting_capability_status?: string|null} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['payments_receivable'] ?? false,
            $data['primary_email_confirmed'] ?? false,
            $data['active_capabilities'] ?? [],
            $data['vaulting_capability_status'] ?? null,
        );
    }

    /** @param array<array-key, mixed> $content */
    public static function fromMerchantIntegration(array $content): self
    {
        return new self(
            (bool) ($content['payments_receivable'] ?? false),
            (bool) ($content['primary_email_confirmed'] ?? false),
            self::activeCapabilitiesOf($content['capabilities'] ?? []),
            self::capabilityStatusOf($content['capabilities'] ?? [], self::VAULTING_CAPABILITY),
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

    public function getVaultingCapabilityStatus(): ?string
    {
        return $this->vaultingCapabilityStatus;
    }

    public function isVaultingEnabled(): bool
    {
        return self::ACTIVE_CAPABILITY_STATUS === $this->vaultingCapabilityStatus;
    }

    /** @return array{payments_receivable: bool, primary_email_confirmed: bool, active_capabilities: list<string>, vaulting_capability_status: string|null} */
    public function toArray(): array
    {
        return [
            'payments_receivable' => $this->paymentsReceivable,
            'primary_email_confirmed' => $this->primaryEmailConfirmed,
            'active_capabilities' => $this->activeCapabilities,
            'vaulting_capability_status' => $this->vaultingCapabilityStatus,
        ];
    }

    /** @return list<string> */
    private static function activeCapabilitiesOf(mixed $capabilities): array
    {
        if (!is_array($capabilities)) {
            return [];
        }

        $active = [];
        foreach ($capabilities as $capability) {
            if (is_array($capability) && isset($capability['name']) && 'ACTIVE' === ($capability['status'] ?? null)) {
                $active[] = (string) $capability['name'];
            }
        }

        return $active;
    }

    private static function capabilityStatusOf(mixed $capabilities, string $name): ?string
    {
        if (!is_array($capabilities)) {
            return null;
        }

        foreach ($capabilities as $capability) {
            if (is_array($capability) && $name === ($capability['name'] ?? null)) {
                return isset($capability['status']) ? (string) $capability['status'] : null;
            }
        }

        return null;
    }
}
