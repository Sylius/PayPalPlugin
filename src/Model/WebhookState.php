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

final readonly class WebhookState
{
    /** @param list<string> $missingEventTypes */
    private function __construct(
        private bool $known,
        private ?string $registeredUrl,
        private bool $pointsElsewhere,
        private array $missingEventTypes,
    ) {
    }

    public static function upToDate(string $registeredUrl): self
    {
        return new self(true, $registeredUrl, false, []);
    }

    public static function unknown(): self
    {
        return new self(false, null, false, []);
    }

    /** @param list<string> $missingEventTypes */
    public static function outdated(string $registeredUrl, bool $pointsElsewhere, array $missingEventTypes): self
    {
        return new self(true, $registeredUrl, $pointsElsewhere, $missingEventTypes);
    }

    public function isUpToDate(): bool
    {
        return $this->known && !$this->pointsElsewhere && [] === $this->missingEventTypes;
    }

    public function isKnown(): bool
    {
        return $this->known;
    }

    public function registeredUrl(): ?string
    {
        return $this->registeredUrl;
    }

    public function pointsElsewhere(): bool
    {
        return $this->pointsElsewhere;
    }

    /** @return list<string> */
    public function missingEventTypes(): array
    {
        return $this->missingEventTypes;
    }
}
