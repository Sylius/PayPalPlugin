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
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Onboarding\Provider\OnboardingModeProvider;

final class OnboardingModeProviderTest extends TestCase
{
    #[Test]
    public function it_takes_the_mode_from_the_onboarding_session(): void
    {
        $sellerNonceManager = $this->createMock(SellerNonceManagerInterface::class);
        $sellerNonceManager->method('isSandbox')->willReturn(true, false);

        $provider = new OnboardingModeProvider($sellerNonceManager);

        self::assertTrue($provider->isSandbox());
        self::assertFalse($provider->isSandbox());
    }
}
