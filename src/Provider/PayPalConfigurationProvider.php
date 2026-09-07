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
        Assert::keyExists($config, 'client_id');

        return (string) $config['client_id'];
    }

    public function getPartnerAttributionId(ChannelInterface $channel): string
    {
        $config = $this->getPayPalPaymentMethodConfig($channel);
        Assert::keyExists($config, 'partner_attribution_id');

        return (string) $config['partner_attribution_id'];
    }

    public function isPayLaterEnabled(ChannelInterface $channel): bool
    {
        return (bool) ($this->getPayPalPaymentMethodConfig($channel)['paylater_enabled'] ?? true);
    }

    public function isVenmoEnabled(ChannelInterface $channel): bool
    {
        return (bool) ($this->getPayPalPaymentMethodConfig($channel)['venmo_enabled'] ?? true);
    }

    public function isMessagingEnabled(ChannelInterface $channel): bool
    {
        return (bool) ($this->getPayPalPaymentMethodConfig($channel)['messaging_enabled'] ?? true);
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
