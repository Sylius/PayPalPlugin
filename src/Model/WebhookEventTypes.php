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

final readonly class WebhookEventTypes
{
    /** @var list<string> */
    public const HANDLED = [
        'CHECKOUT.ORDER.APPROVED',
        'CHECKOUT.ORDER.COMPLETED',
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.DENIED',
        'PAYMENT.CAPTURE.DECLINED',
        'PAYMENT.CAPTURE.PENDING',
        'PAYMENT.CAPTURE.REFUNDED',
        'PAYMENT.CAPTURE.REVERSED',
        'PAYMENT.AUTHORIZATION.CREATED',
        'PAYMENT.AUTHORIZATION.VOIDED',
    ];

    /** @param list<string> $names */
    private function __construct(private array $names)
    {
    }

    public static function handled(): self
    {
        return new self(self::HANDLED);
    }

    /** @param array<array-key, mixed> $names */
    public static function fromNames(array $names): self
    {
        return new self(array_values(array_map(strval(...), array_filter($names, is_scalar(...)))));
    }

    public static function fromPayPal(mixed $eventTypes): self
    {
        if (!is_array($eventTypes)) {
            return new self([]);
        }

        return self::fromNames(array_map(
            static fn (mixed $eventType): mixed => is_array($eventType) ? ($eventType['name'] ?? null) : null,
            $eventTypes,
        ));
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->names;
    }

    /** @return list<string> */
    public function missing(): array
    {
        return array_values(array_diff(self::HANDLED, $this->names));
    }

    /** @return list<array{name: string}> */
    public function toPayPal(): array
    {
        return array_map(static fn (string $name): array => ['name' => $name], $this->names);
    }
}
