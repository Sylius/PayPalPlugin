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

namespace Sylius\PayPalPlugin\Twig;

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PayPalExtension extends AbstractExtension
{
    public function __construct(
        private readonly bool $sandbox,
        private readonly PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_is_paypal_enabled', [$this, 'isPayPalEnabled']),
            new TwigFunction('sylius_is_paypal_sandbox', [$this, 'isSandbox']),
            new TwigFunction('sylius_paypal_is_messaging_enabled', [$this, 'isMessagingEnabled']),
        ];
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function isMessagingEnabled(): bool
    {
        try {
            /** @var ChannelInterface $channel */
            $channel = $this->channelContext->getChannel();

            return $this->fundingSourcesConfigurationProvider->isMessagingEnabled($channel);
        } catch (\InvalidArgumentException) {
            // No PayPal payment method configured for this channel yet.
            return false;
        }
    }

    public function isPayPalEnabled(iterable $paymentMethods): bool
    {
        /** @var PaymentMethodInterface $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            if ($gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
                return true;
            }
        }

        return false;
    }
}
