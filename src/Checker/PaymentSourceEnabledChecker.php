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

use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class PaymentSourceEnabledChecker implements PaymentSourceEnabledCheckerInterface
{
    public function isEnabled(string $paymentSource, PaymentMethodInterface $paymentMethod): bool
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        $redirectPaymentSource = RedirectPaymentSource::tryFrom($paymentSource);
        if (null !== $redirectPaymentSource) {
            return $config->isRedirectPaymentSourceEnabled($redirectPaymentSource);
        }

        return match ($paymentSource) {
            PayPalPaymentSourceProviderInterface::VENMO => $config->isVenmoEnabled(),
            PayPalPaymentSourceProviderInterface::GOOGLE_PAY => $config->isGooglePayEnabled(),
            PayPalPaymentSourceProviderInterface::APPLE_PAY => $config->isApplePayEnabled(),
            default => true,
        };
    }
}
