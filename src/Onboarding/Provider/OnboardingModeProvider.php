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

namespace Sylius\PayPalPlugin\Onboarding\Provider;

final class OnboardingModeProvider implements OnboardingModeProviderInterface
{
    private bool $sandbox = false;

    public function activate(bool $sandbox): void
    {
        $this->sandbox = $sandbox;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }
}
