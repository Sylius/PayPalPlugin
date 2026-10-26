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
use Sylius\Component\Core\Model\OrderItem;
use Sylius\PayPalPlugin\Model\PayPalBillingCycle;
use Sylius\PayPalPlugin\Model\PayPalBillingCycleTenure;
use Sylius\PayPalPlugin\Model\PayPalBillingIntervalUnit;
use Sylius\PayPalPlugin\Model\PayPalBillingPlan;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;

final class PayPalVaultRequestTest extends TestCase
{
    public function test_it_asks_paypal_to_vault_the_wallet_for_the_merchant_on_success(): void
    {
        self::assertSame(
            [
                'store_in_vault' => 'ON_SUCCESS',
                'usage_type' => 'MERCHANT',
                'customer_type' => 'CONSUMER',
                'usage_pattern' => 'SUBSCRIPTION_PREPAID',
                'permit_multiple_payment_tokens' => false,
                'description' => 'Monthly coffee subscription',
            ],
            (new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID, 'Monthly coffee subscription'))->toArray(),
        );
    }

    public function test_it_sends_no_description_when_none_is_given(): void
    {
        self::assertArrayNotHasKey('description', (new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID))->toArray());
    }

    public function test_it_cuts_the_description_to_the_length_paypal_takes(): void
    {
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID, str_repeat('ą', 200));

        self::assertSame(128, mb_strlen((string) $vaultRequest->toArray()['description']));
    }

    public function test_it_refuses_an_empty_usage_pattern(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PayPalVaultRequest('');
    }

    public function test_it_gives_the_billing_plan_of_the_order_item_it_recurs_for(): void
    {
        $billingPlan = new PayPalBillingPlan([new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, totalCycles: 0, price: 999)]);
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID, billingPlans: [7 => $billingPlan]);

        self::assertSame($billingPlan, $vaultRequest->billingPlanFor($this->orderItem(7)));
        self::assertNull($vaultRequest->billingPlanFor($this->orderItem(8)));
        self::assertNull($vaultRequest->billingPlanFor(new OrderItem()));
    }

    public function test_it_keeps_the_billing_plans_out_of_the_vault_attributes(): void
    {
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID, billingPlans: [7 => new PayPalBillingPlan([new PayPalBillingCycle(PayPalBillingCycleTenure::Regular, PayPalBillingIntervalUnit::Month, totalCycles: 0, price: 999)])]);

        self::assertArrayNotHasKey('billing_plan', $vaultRequest->toArray());
    }

    private function orderItem(int $id): OrderItem
    {
        $orderItem = new OrderItem();
        (new \ReflectionProperty(OrderItem::class, 'id'))->setValue($orderItem, $id);

        return $orderItem;
    }
}
