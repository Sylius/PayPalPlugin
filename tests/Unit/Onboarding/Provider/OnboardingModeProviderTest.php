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

namespace Tests\Sylius\PayPalPlugin\Unit\Onboarding\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Onboarding\Provider\OnboardingModeProvider;

final class OnboardingModeProviderTest extends TestCase
{
    #[Test]
    public function it_is_not_sandbox_by_default(): void
    {
        self::assertFalse((new OnboardingModeProvider())->isSandbox());
    }

    #[Test]
    public function it_returns_the_activated_mode(): void
    {
        $provider = new OnboardingModeProvider();

        $provider->activate(true);
        self::assertTrue($provider->isSandbox());

        $provider->activate(false);
        self::assertFalse($provider->isSandbox());
    }
}
