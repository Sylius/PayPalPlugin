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

final readonly class PayPalBillingCycle
{
    private const MAX_TOTAL_CYCLES = 999;

    public function __construct(
        private PayPalBillingCycleTenure $tenure,
        private PayPalBillingIntervalUnit $intervalUnit,
        private int $intervalCount = 1,
        private int $totalCycles = 1,
        private ?int $price = null,
        private PayPalPricingModel $pricingModel = PayPalPricingModel::Fixed,
        private ?\DateTimeImmutable $startDate = null,
    ) {
        Assert::greaterThanEq($intervalCount, 1);
        Assert::range($totalCycles, PayPalBillingCycleTenure::Trial === $tenure ? 1 : 0, self::MAX_TOTAL_CYCLES);
        Assert::nullOrGreaterThanEq($price, 0);
        Assert::false(
            PayPalBillingCycleTenure::Regular === $tenure && null === $price,
            'A regular billing cycle needs a price; only a trial can be free.',
        );
    }

    public function tenure(): PayPalBillingCycleTenure
    {
        return $this->tenure;
    }

    /** @return array<string, mixed> */
    public function toArray(string $currencyCode, int $sequence): array
    {
        $cycle = [
            'tenure_type' => $this->tenure->value,
            'frequency' => [
                'interval_unit' => $this->intervalUnit->value,
                'interval_count' => $this->intervalCount,
            ],
            'total_cycles' => $this->totalCycles,
            'sequence' => $sequence,
        ];

        if (null !== $this->price) {
            $cycle['pricing_scheme'] = [
                'pricing_model' => $this->pricingModel->value,
                'price' => [
                    'value' => AmountUtils::toPayPalValue($this->price, $currencyCode),
                    'currency_code' => $currencyCode,
                ],
            ];
        }

        if (null !== $this->startDate) {
            $cycle['start_date'] = $this->startDate->format('Y-m-d');
        }

        return $cycle;
    }
}
