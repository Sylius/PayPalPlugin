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

final readonly class WebhookRegistration
{
    private const REGISTERED = 'registered';

    private const UPDATED = 'updated';

    private const UP_TO_DATE = 'up_to_date';

    private function __construct(
        private string $id,
        private string $url,
        private string $outcome,
    ) {
    }

    public static function registered(string $id, string $url): self
    {
        return new self($id, $url, self::REGISTERED);
    }

    public static function updated(string $id, string $url): self
    {
        return new self($id, $url, self::UPDATED);
    }

    public static function upToDate(string $id, string $url): self
    {
        return new self($id, $url, self::UP_TO_DATE);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function isNew(): bool
    {
        return self::REGISTERED === $this->outcome;
    }

    public function isUpToDate(): bool
    {
        return self::UP_TO_DATE === $this->outcome;
    }
}
