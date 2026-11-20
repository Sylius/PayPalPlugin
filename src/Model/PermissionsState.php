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
        private PermissionsStatus $status,
        private array $missing,
    ) {
    }

    public static function upToDate(): self
    {
        return new self(PermissionsStatus::UpToDate, []);
    }

    public static function unknown(): self
    {
        return new self(PermissionsStatus::Unknown, []);
    }

    /** @param non-empty-list<string> $missing */
    public static function outdated(array $missing): self
    {
        return new self(PermissionsStatus::OutOfDate, $missing);
    }

    public function status(): PermissionsStatus
    {
        return $this->status;
    }

    public function isUpToDate(): bool
    {
        return PermissionsStatus::UpToDate === $this->status;
    }

    public function isKnown(): bool
    {
        return PermissionsStatus::Unknown !== $this->status;
    }

    /** @return list<string> */
    public function missing(): array
    {
        return $this->missing;
    }
}
