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
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Model\OnboardingScope;

final class OnboardingScopeTest extends TestCase
{
    public function test_it_turns_into_an_array_and_back(): void
    {
        $array = [
            'product' => 'ppcp',
            'secondary_products' => ['payment_methods'],
            'capabilities' => [Capability::APPLE_PAY],
            'features' => ['payment', 'refund'],
        ];
        $scope = new OnboardingScope($array['product'], $array['secondary_products'], $array['capabilities'], $array['features']);

        self::assertSame($array, $scope->toArray());
        self::assertEquals($scope, OnboardingScope::fromArray($array));
    }

    public function test_it_reads_a_partial_or_malformed_array_as_an_empty_scope_where_it_lacks_data(): void
    {
        $scope = OnboardingScope::fromArray(['capabilities' => [Capability::APPLE_PAY, 7, [Capability::GOOGLE_PAY]], 'features' => 'payment']);

        self::assertSame('', $scope->product());
        self::assertSame([], $scope->secondaryProducts());
        self::assertSame([Capability::APPLE_PAY, '7'], $scope->capabilities());
        self::assertSame([], $scope->features());
    }

    public function test_it_is_covered_by_a_scope_that_has_all_it_asks_for(): void
    {
        $scope = new OnboardingScope('ppcp', ['payment_methods'], [Capability::APPLE_PAY], ['payment']);

        self::assertSame([], $scope->notCoveredBy($scope));
        self::assertSame([], $scope->notCoveredBy(new OnboardingScope('ppcp', ['payment_methods'], [Capability::APPLE_PAY, 'VAULT'], ['payment', 'refund'])));
    }

    public function test_it_is_covered_by_a_scope_that_spells_the_same_names_in_another_case(): void
    {
        $scope = new OnboardingScope('ppcp', ['payment_methods'], ['PAYPAL_WALLET_VAULTING_ADVANCED'], ['payment']);

        self::assertSame([], $scope->notCoveredBy(new OnboardingScope('PPCP', ['PAYMENT_METHODS'], ['paypal_wallet_vaulting_advanced'], ['PAYMENT'])));
    }

    public function test_it_lists_what_a_scope_lacks_of_it(): void
    {
        $scope = new OnboardingScope('ppcp', ['payment_methods'], [Capability::APPLE_PAY, Capability::GOOGLE_PAY], ['payment', 'refund']);

        self::assertSame(
            ['ppcp', 'payment_methods', Capability::GOOGLE_PAY, 'refund'],
            $scope->notCoveredBy(new OnboardingScope('express_checkout', [], [Capability::APPLE_PAY], ['payment'])),
        );
    }
}
