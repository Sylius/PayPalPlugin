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

use Sylius\Component\Core\Model\OrderItemInterface;
use Webmozart\Assert\Assert;

final readonly class PayPalVaultRequest
{
    public const USAGE_PATTERN_SUBSCRIPTION_PREPAID = 'SUBSCRIPTION_PREPAID';

    private const DESCRIPTION_MAX_LENGTH = 128;

    /** @param array<int, PayPalBillingPlan> $billingPlans */
    public function __construct(
        private string $usagePattern,
        private ?string $description = null,
        private array $billingPlans = [],
    ) {
        Assert::notEmpty($usagePattern);
    }

    public function usagePattern(): string
    {
        return $this->usagePattern;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function billingPlanFor(OrderItemInterface $orderItem): ?PayPalBillingPlan
    {
        $orderItemId = $orderItem->getId();

        return is_int($orderItemId) ? ($this->billingPlans[$orderItemId] ?? null) : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'store_in_vault' => 'ON_SUCCESS',
            'usage_type' => 'MERCHANT',
            'customer_type' => 'CONSUMER',
            'usage_pattern' => $this->usagePattern,
            'permit_multiple_payment_tokens' => false,
            'description' => null !== $this->description ? mb_substr($this->description, 0, self::DESCRIPTION_MAX_LENGTH) : null,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
