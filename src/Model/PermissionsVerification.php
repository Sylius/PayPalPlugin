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

    /** @param array<string, string> $pendingCapabilities */
    private function __construct(
        private string $outcome,
        private array $pendingCapabilities,
    ) {
    }

    public static function upToDate(): self
    {
        return new self(self::UP_TO_DATE, []);
    }

    /** @param non-empty-array<string, string> $pendingCapabilities */
    public static function awaitingPayPal(array $pendingCapabilities): self
    {
        return new self(self::AWAITING_PAYPAL, $pendingCapabilities);
    }

    public static function consentRequired(): self
    {
        return new self(self::CONSENT_REQUIRED, []);
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
}
