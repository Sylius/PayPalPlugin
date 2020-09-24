<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Webmozart\Assert\Assert;

final class PayPalConfigurationProvider implements PayPalConfigurationProviderInterface
{
    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    /** @var ChannelContextInterface */
    private $channelContext;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ChannelContextInterface $channelContext
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->channelContext = $channelContext;
    }

    public function getClientId(): string
    {
        $config = $this->getPayPalPaymentMethodConfig();
        Assert::keyExists($config, 'client_id');

        return (string) $config['client_id'];
    }

    public function getPartnerAttributionId(): string
    {
        $config = $this->getPayPalPaymentMethodConfig();
        Assert::keyExists($config, 'partner_attribution_id');

        return (string) $config['partner_attribution_id'];
    }

    public function getApiBaseUrl(): string
    {
        $config = $this->getPayPalPaymentMethodConfig();
        Assert::keyExists($config, 'sandbox');

        return ((bool) $config['sandbox']) ? 'https://api.sandbox.paypal.com/' : 'https://api.paypal.com/';
    }

    public function getFacilitatorUrl(): string
    {
        $config = $this->getPayPalPaymentMethodConfig();
        Assert::keyExists($config, 'sandbox');

        return ((bool) $config['sandbox']) ? 'https://paypal.sylius.com' : 'https://prod.paypal.sylius.com';
    }

    private function getPayPalPaymentMethodConfig(): array
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();
        $methods = $this->paymentMethodRepository->findEnabledForChannel($channel);

        /** @var PaymentMethodInterface $method */
        foreach ($methods as $method) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $method->getGatewayConfig();

            if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
                continue;
            }

            return $gatewayConfig->getConfig();
        }

        throw new \InvalidArgumentException('No PayPal payment method defined');
    }
}
