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

final readonly class PayPalConfigurationProvider implements PayPalConfigurationProviderInterface, PayPalFundingSourcesConfigurationProviderInterface
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(private PaymentMethodRepositoryInterface $paymentMethodRepository)
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
        $methods = $this->paymentMethodRepository->findEnabledForChannel($channel);

        /** @var PaymentMethodInterface $method */
        foreach ($methods as $method) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $method->getGatewayConfig();

            if ($gatewayConfig->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
                continue;
            }

            return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
        }

        throw new \InvalidArgumentException('No PayPal payment method defined');
    }
}
