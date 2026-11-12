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
    private const UP_TO_DATE = 'up_to_date';

    private const AWAITING_PAYPAL = 'awaiting_paypal';

    private const CONSENT_REQUIRED = 'consent_required';

    /**
     * @param array<string, string> $pendingCapabilities
     * @param list<string> $missingCapabilities
     */
    private function __construct(
        private string $outcome,
        private array $pendingCapabilities = [],
        private array $missingCapabilities = [],
    ) {
    }

    public static function of(OnboardingStatus $status, OnboardingScope $scope): self
    {
        $pendingCapabilities = [];
        $missingCapabilities = [];
        foreach ($scope->capabilities() as $capability) {
            $capabilityStatus = $status->capabilityStatus($capability);
            if (null === $capabilityStatus) {
                $missingCapabilities[] = $capability;
            } elseif (OnboardingStatus::ACTIVE_CAPABILITY_STATUS !== $capabilityStatus) {
                $pendingCapabilities[$capability] = $capabilityStatus;
            }
        }

        if ([] !== $missingCapabilities) {
            return self::consentRequired($missingCapabilities);
        }

        if ([] !== $pendingCapabilities) {
            return self::awaitingPayPal($pendingCapabilities);
        }

        return self::upToDate();
    }

    public static function upToDate(): self
    {
        return new self(self::UP_TO_DATE);
    }

    /** @param non-empty-array<string, string> $pendingCapabilities */
    public static function awaitingPayPal(array $pendingCapabilities): self
    {
        return new self(self::AWAITING_PAYPAL, $pendingCapabilities);
    }

    /** @param list<string> $missingCapabilities */
    public static function consentRequired(array $missingCapabilities = []): self
    {
        return new self(self::CONSENT_REQUIRED, missingCapabilities: $missingCapabilities);
    }

    public function isUpToDate(): bool
    {
        return self::UP_TO_DATE === $this->outcome;
    }

    public function isAwaitingPayPal(): bool
    {
        return self::AWAITING_PAYPAL === $this->outcome;
    }

    public function isConsentRequired(): bool
    {
        return self::CONSENT_REQUIRED === $this->outcome;
    }

    /** @return array<string, string> the capabilities PayPal has not activated yet, with their status */
    public function pendingCapabilities(): array
    {
        return $this->pendingCapabilities;
    }

    /** @return list<string> the capabilities the PayPal account does not have at all */
    public function missingCapabilities(): array
    {
        return $this->missingCapabilities;
    }
}
