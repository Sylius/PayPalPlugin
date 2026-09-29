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

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Webmozart\Assert\Assert;

final readonly class PayPalConfigurationProvider implements PayPalConfigurationProviderInterface, PayPalFundingSourcesConfigurationProviderInterface
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(private PaymentMethodRepositoryInterface $paymentMethodRepository)
    {
    }

    public function getClientId(ChannelInterface $channel): string
    {
        $config = $this->getPayPalPaymentMethodConfig($channel);
        Assert::keyExists($config, PayPalGatewayConfig::CLIENT_ID);

        return (string) $config[PayPalGatewayConfig::CLIENT_ID];
    }

    public function getPartnerAttributionId(ChannelInterface $channel): string
    {
        $config = $this->getPayPalPaymentMethodConfig($channel);
        Assert::keyExists($config, PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID);

        return (string) $config[PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID];
    }

    public function isPayLaterEnabled(ChannelInterface $channel): bool
    {
        return (bool) ($this->getPayPalPaymentMethodConfig($channel)[PayPalGatewayConfig::PAY_LATER_ENABLED] ?? true);
    }

    public function isMessagingEnabled(ChannelInterface $channel): bool
    {
        if (!$this->isPayLaterEnabled($channel)) {
            return false;
        }

        return (bool) ($this->getPayPalPaymentMethodConfig($channel)[PayPalGatewayConfig::MESSAGING_ENABLED] ?? true);
    }

    public function isGooglePayEnabled(ChannelInterface $channel): bool
    {
        return (bool) ($this->getPayPalPaymentMethodConfig($channel)[PayPalGatewayConfig::GOOGLE_PAY_ENABLED] ?? false);
    }

    public function isApplePayEnabled(ChannelInterface $channel): bool
    {
        return (bool) ($this->getPayPalPaymentMethodConfig($channel)[PayPalGatewayConfig::APPLE_PAY_ENABLED] ?? false);
    }

    public function isTrustlyEnabled(ChannelInterface $channel): bool
    {
        return (bool) ($this->getPayPalPaymentMethodConfig($channel)[RedirectPaymentSource::Trustly->configurationKey()] ?? false);
    }

    private function getPayPalPaymentMethodConfig(ChannelInterface $channel): array
    {
        $methods = $this->paymentMethodRepository->findEnabledForChannel($channel);

        /** @var PaymentMethodInterface $method */
        foreach ($methods as $method) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $method->getGatewayConfig();

            if ($gatewayConfig->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
                continue;
            }

            return $gatewayConfig->getConfig();
        }

        throw new \InvalidArgumentException('No PayPal payment method defined');
    }
}
