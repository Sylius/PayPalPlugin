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
    public const ACTIVE_CAPABILITY_STATUS = 'ACTIVE';

    /** @var array<string, string> */
    private array $capabilityStatuses;

    /** @param array<string, string> $capabilityStatuses */
    public function __construct(
        private bool $paymentsReceivable,
        private bool $primaryEmailConfirmed,
        array $capabilityStatuses = [],
    ) {
        $this->capabilityStatuses = array_map(strtoupper(...), array_change_key_case($capabilityStatuses, \CASE_UPPER));
    }

    /** @param array<array-key, mixed> $content */
    public static function fromMerchantIntegration(array $content): self
    {
        return new self(
            (bool) ($content['payments_receivable'] ?? false),
            (bool) ($content['primary_email_confirmed'] ?? false),
            self::capabilityStatusesOf($content['capabilities'] ?? []),
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
        return array_map(
            strval(...),
            array_keys(array_filter(
                $this->capabilityStatuses,
                static fn (string $status): bool => self::ACTIVE_CAPABILITY_STATUS === $status,
            )),
        );
    }

    public function hasActiveCapability(string $capability): bool
    {
        return self::ACTIVE_CAPABILITY_STATUS === $this->capabilityStatus($capability);
    }

    public function capabilityStatus(string $capability): ?string
    {
        return $this->capabilityStatuses[strtoupper($capability)] ?? null;
    }

    /** @return array<string, string> */
    private static function capabilityStatusesOf(mixed $capabilities): array
    {
        if (!is_array($capabilities)) {
            return [];
        }

        $statuses = [];
        foreach ($capabilities as $capability) {
            if (is_array($capability) && is_string($capability['name'] ?? null) && is_string($capability['status'] ?? null)) {
                $statuses[$capability['name']] = $capability['status'];
            }
        }

        return $statuses;
    }
}
