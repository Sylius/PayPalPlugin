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

use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Provider\ActiveModeProviderInterface;

final readonly class OnboardingModeProvider implements ActiveModeProviderInterface
{
    public function __construct(private SellerNonceManagerInterface $sellerNonceManager)
    {
    }

    public function isSandbox(): bool
    {
        return $this->sellerNonceManager->isSandbox();
    }
}
