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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\PayPalBillingCycle;
use Sylius\PayPalPlugin\Model\PayPalBillingCycleTenure;
use Sylius\PayPalPlugin\Model\PayPalBillingIntervalUnit;
use Sylius\PayPalPlugin\Model\PayPalBillingPlan;
use Sylius\PayPalPlugin\Model\PayPalPricingModel;

final class PayPalBillingPlanTest extends TestCase
{
    public function test_it_describes_a_monthly_plan_with_a_setup_fee(): void
    {
        $billingPlan = new PayPalBillingPlan(
            [new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, totalCycles: 0, price: 999)],
            setupFee: 1500,
            name: 'Coffee subscription',
        );

        self::assertSame([
            'name' => 'Coffee subscription',
            'setup_fee' => ['value' => '15.00', 'currency_code' => 'USD'],
            'billing_cycles' => [
                [
                    'tenure_type' => 'REGULAR',
                    'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
                    'total_cycles' => 0,
                    'sequence' => 1,
                    'pricing_scheme' => [
                        'pricing_model' => 'FIXED',
                        'price' => ['value' => '9.99', 'currency_code' => 'USD'],
                    ],
                ],
            ],
        ], $billingPlan->toArray('USD'));
    }

    public function test_it_runs_the_trials_before_the_regular_cycle(): void
    {
        $billingPlan = new PayPalBillingPlan([
            new PayPalBillingCycle(PayPalBillingCycleTenure::Trial, PayPalBillingIntervalUnit::Week, intervalCount: 2),
            new PayPalBillingCycle(PayPalBillingCycleTenure::Trial, PayPalBillingIntervalUnit::Month, totalCycles: 3, price: 500),
            new PayPalBillingCycle(
                PayPalBillingCycleTenure::Regular,
                PayPalBillingIntervalUnit::Month,
                totalCycles: 12,
                price: 1000,
                pricingModel: PayPalPricingModel::Variable,
                startDate: new \DateTimeImmutable('2026-12-01 10:00:00'),
            ),
        ]);

        self::assertSame([
            'billing_cycles' => [
                [
                    'tenure_type' => 'TRIAL',
                    'frequency' => ['interval_unit' => 'WEEK', 'interval_count' => 2],
                    'total_cycles' => 1,
                    'sequence' => 1,
                ],
                [
                    'tenure_type' => 'TRIAL',
                    'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
                    'total_cycles' => 3,
                    'sequence' => 2,
                    'pricing_scheme' => [
                        'pricing_model' => 'FIXED',
                        'price' => ['value' => '5.00', 'currency_code' => 'EUR'],
                    ],
                ],
                [
                    'tenure_type' => 'REGULAR',
                    'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
                    'total_cycles' => 12,
                    'sequence' => 3,
                    'pricing_scheme' => [
                        'pricing_model' => 'VARIABLE',
                        'price' => ['value' => '10.00', 'currency_code' => 'EUR'],
                    ],
                    'start_date' => '2026-12-01',
                ],
            ],
        ], $billingPlan->toArray('EUR'));
    }

    public function test_it_formats_the_amounts_of_a_zero_decimal_currency(): void
    {
        $billingPlan = new PayPalBillingPlan(
            [new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, price: 120000)],
            setupFee: 50000,
        );

        $payPalBillingPlan = $billingPlan->toArray('JPY');

        self::assertSame('500', $payPalBillingPlan['setup_fee']['value']);
        self::assertSame('1200', $payPalBillingPlan['billing_cycles'][0]['pricing_scheme']['price']['value']);
    }

    public function test_it_strips_the_characters_paypal_refuses_from_the_name(): void
    {
        $billingPlan = new PayPalBillingPlan(
            [new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Year, price: 100)],
            name: 'Kawa "Premium" & więcej: 12 mies.',
        );

        self::assertSame('Kawa Premium  wicej: 12 mies.', $billingPlan->toArray('PLN')['name']);
    }

    public function test_it_sends_no_name_that_has_nothing_left_paypal_accepts(): void
    {
        $billingPlan = new PayPalBillingPlan(
            [new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Year, price: 100)],
            name: '★★★',
        );

        self::assertArrayNotHasKey('name', $billingPlan->toArray('PLN'));
    }

    public function test_it_needs_a_billing_cycle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PayPalBillingPlan([]);
    }

    public function test_it_needs_to_end_with_a_regular_billing_cycle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PayPalBillingPlan([new PayPalBillingCycle(PayPalBillingCycleTenure::Trial, PayPalBillingIntervalUnit::Month)]);
    }

    public function test_it_allows_only_one_regular_billing_cycle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PayPalBillingPlan([
            new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, price: 100),
            new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Year, price: 1000),
        ]);
    }

    public function test_it_allows_at_most_two_trials(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PayPalBillingPlan([
            new PayPalBillingCycle(PayPalBillingCycleTenure::Trial, PayPalBillingIntervalUnit::Week),
            new PayPalBillingCycle(PayPalBillingCycleTenure::Trial, PayPalBillingIntervalUnit::Week),
            new PayPalBillingCycle(PayPalBillingCycleTenure::Trial, PayPalBillingIntervalUnit::Week),
            new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, price: 100),
        ]);
    }

    public function test_it_refuses_a_negative_setup_fee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PayPalBillingPlan([new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, price: 100)], setupFee: -1);
    }
}
