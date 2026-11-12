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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PermissionsState;

final readonly class PermissionsStateChecker implements PermissionsStateCheckerInterface
{
    public function check(PaymentMethodInterface $paymentMethod): PermissionsState
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $scope = null === $gatewayConfig ? null : PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->onboardingScope();
        if (null === $scope) {
            return PermissionsState::unknown();
        }

        $missing = OnboardingScope::current()->notCoveredBy($scope);

        return [] === $missing ? PermissionsState::upToDate() : PermissionsState::outdated($missing);
    }
}
