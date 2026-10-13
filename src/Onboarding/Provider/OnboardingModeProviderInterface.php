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

use Sylius\PayPalPlugin\Provider\ActiveModeProviderInterface;

interface OnboardingModeProviderInterface extends ActiveModeProviderInterface
{
    public function activate(bool $sandbox): void;
}
