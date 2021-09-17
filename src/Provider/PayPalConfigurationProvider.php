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
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Webmozart\Assert\Assert;

final class PayPalConfigurationProvider implements PayPalConfigurationProviderInterface
{
    private PaymentMethodRepositoryInterface $paymentMethodRepository;

    public function __construct(PaymentMethodRepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
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

    private function getPayPalPaymentMethodConfig(ChannelInterface $channel): array
    {
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
