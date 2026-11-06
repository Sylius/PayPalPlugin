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
        private bool $registered,
        private ?string $registeredUrl,
        private bool $pointsElsewhere,
        private array $missingEventTypes,
    ) {
    }

    public static function upToDate(string $registeredUrl): self
    {
        return new self(true, $registeredUrl, false, []);
    }

    public static function notRegistered(): self
    {
        return new self(false, null, false, []);
    }

    /** @param list<string> $missingEventTypes */
    public static function outdated(string $registeredUrl, bool $pointsElsewhere, array $missingEventTypes): self
    {
        return new self(true, $registeredUrl, $pointsElsewhere, $missingEventTypes);
    }

    /** @param array{registered: bool, registered_url: ?string, points_elsewhere: bool, missing_event_types: list<string>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['registered'], $data['registered_url'], $data['points_elsewhere'], $data['missing_event_types']);
    }

    /** @return array{registered: bool, registered_url: ?string, points_elsewhere: bool, missing_event_types: list<string>} */
    public function toArray(): array
    {
        return [
            'registered' => $this->registered,
            'registered_url' => $this->registeredUrl,
            'points_elsewhere' => $this->pointsElsewhere,
            'missing_event_types' => $this->missingEventTypes,
        ];
    }

    public function isUpToDate(): bool
    {
        return $this->registered && !$this->pointsElsewhere && [] === $this->missingEventTypes;
    }

    public function isRegistered(): bool
    {
        return $this->registered;
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
