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

    /** @param array<array-key, mixed> $content */
    public static function fromMerchantIntegration(array $content): self
    {
        return new self(
            (bool) ($content['payments_receivable'] ?? false),
            (bool) ($content['primary_email_confirmed'] ?? false),
            self::activeCapabilitiesOf($content['capabilities'] ?? []),
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
}
