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

final readonly class PermissionsState
{
    /** @param list<string> $missing */
    private function __construct(
        private bool $known,
        private array $missing,
    ) {
    }

    public static function upToDate(): self
    {
        return new self(true, []);
    }

    public static function unknown(): self
    {
        return new self(false, []);
    }

    /** @param list<string> $missing */
    public static function outdated(array $missing): self
    {
        return new self(true, $missing);
    }

    public function isUpToDate(): bool
    {
        return $this->known && [] === $this->missing;
    }

    public function isKnown(): bool
    {
        return $this->known;
    }

    /** @return list<string> */
    public function missing(): array
    {
        return $this->missing;
    }
}
