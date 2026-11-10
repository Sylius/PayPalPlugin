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

namespace Sylius\PayPalPlugin\Updater;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class OnboardingScopeUpdater implements OnboardingScopeUpdaterInterface
{
    public function __construct(private ObjectManager $paymentMethodManager)
    {
    }

    public function update(PaymentMethodInterface $paymentMethod): void
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            return;
        }

        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), [
            PayPalGatewayConfig::ONBOARDING_SCOPE => OnboardingScope::current()->toArray(),
        ]));
        $this->paymentMethodManager->flush();
    }
}
