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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;

final readonly class EnabledPaymentSourcesProvider implements EnabledPaymentSourcesProviderInterface
{
    public function provide(PaymentMethodInterface $paymentMethod): array
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            return [];
        }

        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        $paymentSources = ['paypal'];
        if ($config->isPayLaterEnabled()) {
            $paymentSources[] = 'pay_later';
        }
        if ($config->isVenmoEnabled()) {
            $paymentSources[] = 'venmo';
        }
        if ($config->isApplePayEnabled()) {
            $paymentSources[] = 'apple_pay';
        }
        if ($config->isGooglePayEnabled()) {
            $paymentSources[] = 'google_pay';
        }
        foreach (RedirectPaymentSource::cases() as $redirectPaymentSource) {
            if ($config->isRedirectPaymentSourceEnabled($redirectPaymentSource)) {
                $paymentSources[] = $redirectPaymentSource->value;
            }
        }
        array_push($paymentSources, 'visa', 'master_card', 'amex');

        return $paymentSources;
    }
}
