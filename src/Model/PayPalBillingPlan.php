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

use Sylius\PayPalPlugin\AmountUtils;
use Webmozart\Assert\Assert;

final readonly class PayPalBillingPlan
{
    private const MAX_TRIAL_CYCLES = 2;

    private const NAME_MAX_LENGTH = 127;

    private const NAME_DISALLOWED_CHARACTERS = '/[^A-Za-z0-9() +\',.:-]/';

    /** @param list<PayPalBillingCycle> $billingCycles */
    public function __construct(
        private array $billingCycles,
        private ?int $setupFee = null,
        private ?string $name = null,
    ) {
        Assert::isList($billingCycles);
        Assert::notEmpty($billingCycles);

        $trialCycles = array_slice($billingCycles, 0, -1);
        Assert::maxCount($trialCycles, self::MAX_TRIAL_CYCLES);
        foreach ($trialCycles as $trialCycle) {
            Assert::same($trialCycle->tenure(), PayPalBillingCycleTenure::Trial, 'Only trial billing cycles can come before the regular one.');
        }
        Assert::same(end($billingCycles)->tenure(), PayPalBillingCycleTenure::Regular, 'A billing plan ends with exactly one regular billing cycle.');

        Assert::nullOrGreaterThanEq($setupFee, 0);
    }

    /** @return array<string, mixed> */
    public function toArray(string $currencyCode): array
    {
        $billingPlan = [];

        $name = $this->payPalName();
        if (null !== $name) {
            $billingPlan['name'] = $name;
        }

        if (null !== $this->setupFee) {
            $billingPlan['setup_fee'] = [
                'value' => AmountUtils::toPayPalValue($this->setupFee, $currencyCode),
                'currency_code' => $currencyCode,
            ];
        }

        $billingPlan['billing_cycles'] = array_map(
            static fn (PayPalBillingCycle $billingCycle, int $index): array => $billingCycle->toArray($currencyCode, $index + 1),
            $this->billingCycles,
            array_keys($this->billingCycles),
        );

        return $billingPlan;
    }

    private function payPalName(): ?string
    {
        if (null === $this->name) {
            return null;
        }

        $name = mb_substr(trim((string) preg_replace(self::NAME_DISALLOWED_CHARACTERS, '', $this->name)), 0, self::NAME_MAX_LENGTH);

        return '' === $name ? null : $name;
    }
}
