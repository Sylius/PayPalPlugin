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

final readonly class PermissionsVerification
{
    private const STATUSES_A_CONSENT_DOES_NOT_CHANGE = ['PENDING', 'IN_REVIEW', 'NEED_DATA', 'SUSPENDED', 'REVOKED', 'DENY'];

    /**
     * @param array<string, string> $pendingCapabilities
     * @param list<string> $capabilitiesToRequest
     */
    private function __construct(
        private PermissionsVerificationOutcome $outcome,
        private array $pendingCapabilities = [],
        private array $capabilitiesToRequest = [],
    ) {
    }

    public static function of(OnboardingStatus $status, OnboardingScope $scope): self
    {
        $pendingCapabilities = [];
        $capabilitiesToRequest = [];
        foreach ($scope->capabilities() as $capability) {
            $capabilityStatus = strtoupper((string) $status->capabilityStatus($capability));
            if (OnboardingStatus::ACTIVE_CAPABILITY_STATUS === $capabilityStatus) {
                continue;
            }

            if (in_array($capabilityStatus, self::STATUSES_A_CONSENT_DOES_NOT_CHANGE, true)) {
                $pendingCapabilities[$capability] = $capabilityStatus;
            } else {
                $capabilitiesToRequest[] = $capability;
            }
        }

        if ([] !== $capabilitiesToRequest) {
            return self::consentRequired($capabilitiesToRequest);
        }

        if ([] !== $pendingCapabilities) {
            return self::awaitingPayPal($pendingCapabilities);
        }

        return self::upToDate();
    }

    public static function upToDate(): self
    {
        return new self(PermissionsVerificationOutcome::UpToDate);
    }

    /** @param non-empty-array<string, string> $pendingCapabilities */
    public static function awaitingPayPal(array $pendingCapabilities): self
    {
        return new self(PermissionsVerificationOutcome::AwaitingPayPal, $pendingCapabilities);
    }

    /** @param list<string> $capabilitiesToRequest */
    public static function consentRequired(array $capabilitiesToRequest = []): self
    {
        return new self(PermissionsVerificationOutcome::ConsentRequired, capabilitiesToRequest: $capabilitiesToRequest);
    }

    public function outcome(): PermissionsVerificationOutcome
    {
        return $this->outcome;
    }

    public function isUpToDate(): bool
    {
        return PermissionsVerificationOutcome::UpToDate === $this->outcome;
    }

    public function isAwaitingPayPal(): bool
    {
        return PermissionsVerificationOutcome::AwaitingPayPal === $this->outcome;
    }

    public function isConsentRequired(): bool
    {
        return PermissionsVerificationOutcome::ConsentRequired === $this->outcome;
    }

    /** @return array<string, string> the capabilities a consent would not activate, with their status, e.g. PENDING or NEED_DATA */
    public function pendingCapabilities(): array
    {
        return $this->pendingCapabilities;
    }

    /** @return list<string> */
    public function describedPendingCapabilities(): array
    {
        return array_map(
            static fn (string $capability, string $status): string => $capability . ': ' . $status,
            array_keys($this->pendingCapabilities),
            $this->pendingCapabilities,
        );
    }

    /** @return list<string> the capabilities the PayPal account does not have, or has but may get through a consent, e.g. INACTIVE */
    public function capabilitiesToRequest(): array
    {
        return $this->capabilitiesToRequest;
    }
}
