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
use Sylius\PayPalPlugin\Model\OnboardingScope;

final class OnboardingScopeTest extends TestCase
{
    public function test_it_turns_into_an_array_and_back(): void
    {
        $scope = new OnboardingScope('ppcp', ['payment_methods'], ['APPLE_PAY'], ['payment', 'refund']);

        self::assertSame([
            'product' => 'ppcp',
            'secondary_products' => ['payment_methods'],
            'capabilities' => ['APPLE_PAY'],
            'features' => ['payment', 'refund'],
        ], $scope->toArray());
        self::assertEquals($scope, OnboardingScope::fromArray($scope->toArray()));
    }

    public function test_it_reads_a_partial_or_malformed_array_as_an_empty_scope_where_it_lacks_data(): void
    {
        $scope = OnboardingScope::fromArray(['capabilities' => ['APPLE_PAY', 7, ['GOOGLE_PAY']], 'features' => 'payment']);

        self::assertSame('', $scope->product());
        self::assertSame([], $scope->secondaryProducts());
        self::assertSame(['APPLE_PAY', '7'], $scope->capabilities());
        self::assertSame([], $scope->features());
    }
}
