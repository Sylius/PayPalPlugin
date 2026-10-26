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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\PayPalBillingCycle;
use Sylius\PayPalPlugin\Model\PayPalBillingCycleTenure;
use Sylius\PayPalPlugin\Model\PayPalBillingIntervalUnit;

final class PayPalBillingCycleTest extends TestCase
{
    public function test_it_lets_a_regular_cycle_run_without_end(): void
    {
        $billingCycle = new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, totalCycles: 0, price: 100);

        self::assertSame(0, $billingCycle->toArray('USD', 1)['total_cycles']);
    }

    public function test_it_sends_no_pricing_scheme_for_a_free_trial(): void
    {
        $billingCycle = new PayPalBillingCycle(PayPalBillingCycleTenure::Trial, PayPalBillingIntervalUnit::Day, intervalCount: 14);

        self::assertArrayNotHasKey('pricing_scheme', $billingCycle->toArray('USD', 1));
    }

    /** @return iterable<string, array{\Closure(): PayPalBillingCycle}> */
    public static function invalidCycles(): iterable
    {
        yield 'free regular cycle' => [static fn (): PayPalBillingCycle => new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month)];
        yield 'endless trial' => [static fn (): PayPalBillingCycle => new PayPalBillingCycle(PayPalBillingCycleTenure::Trial, PayPalBillingIntervalUnit::Month, totalCycles: 0)];
        yield 'too many cycles' => [static fn (): PayPalBillingCycle => new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, totalCycles: 1000, price: 100)];
        yield 'no interval' => [static fn (): PayPalBillingCycle => new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, intervalCount: 0, price: 100)];
        yield 'negative price' => [static fn (): PayPalBillingCycle => new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, price: -1)];
    }

    #[DataProvider('invalidCycles')]
    public function test_it_refuses_a_cycle_paypal_would_reject(\Closure $createBillingCycle): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $createBillingCycle();
    }
}
