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

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;

final readonly class PayPalConfigurationProvider implements PayPalConfigurationProviderInterface, PayPalFundingSourcesConfigurationProviderInterface
{
    public function __construct(private PayPalPaymentMethodsResolverInterface $payPalPaymentMethodsResolver)
    {
    }

    public function getClientId(ChannelInterface $channel): string
    {
        return $this->getPayPalPaymentMethodConfig($channel)->clientId();
    }

    public function getPartnerAttributionId(ChannelInterface $channel): string
    {
        return $this->getPayPalPaymentMethodConfig($channel)->partnerAttributionId();
    }

    public function isPayLaterEnabled(ChannelInterface $channel): bool
    {
        return $this->getPayPalPaymentMethodConfig($channel)->isPayLaterEnabled();
    }

    public function isMessagingEnabled(ChannelInterface $channel): bool
    {
        $config = $this->getPayPalPaymentMethodConfig($channel);

        return $config->isPayLaterEnabled() && $config->isMessagingEnabled();
    }

    public function isVenmoEnabled(ChannelInterface $channel): bool
    {
        return $this->getPayPalPaymentMethodConfig($channel)->isVenmoEnabled();
    }

    public function isGooglePayEnabled(ChannelInterface $channel): bool
    {
        return $this->getPayPalPaymentMethodConfig($channel)->isGooglePayEnabled();
    }

    public function isApplePayEnabled(ChannelInterface $channel): bool
    {
        return $this->getPayPalPaymentMethodConfig($channel)->isApplePayEnabled();
    }

    public function isTrustlyEnabled(ChannelInterface $channel): bool
    {
        return $this->getPayPalPaymentMethodConfig($channel)->isRedirectPaymentSourceEnabled(RedirectPaymentSource::Trustly);
    }

    private function getPayPalPaymentMethodConfig(ChannelInterface $channel): PayPalGatewayConfig
    {
        $paymentMethod = $this->payPalPaymentMethodsResolver->getInChannel($channel)[0] ?? null;
        $gatewayConfig = $paymentMethod?->getGatewayConfig();
        if (null === $gatewayConfig) {
            throw new \InvalidArgumentException('No PayPal payment method defined');
        }

        return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
    }
}
