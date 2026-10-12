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

namespace Sylius\PayPalPlugin\Onboarding\Checker;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Manager\CredentialsManagerInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;

final readonly class ProductionSellerOnboardedChecker implements ProductionSellerOnboardedCheckerInterface
{
    public function __construct(
        private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        private CredentialsManagerInterface $credentialsManager,
    ) {
    }

    public function isOnboarded(): bool
    {
        if (!$this->payPalPaymentMethodProvider->exists()) {
            return false;
        }

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $this->payPalPaymentMethodProvider->provide()->getGatewayConfig();

        return $this->credentialsManager->hasCredentials($gatewayConfig->getConfig(), false);
    }
}
