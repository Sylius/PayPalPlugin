<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PayPalConfigurationProviderInterface
{
    public function getClientId(ChannelInterface $channel): string;

    public function getPartnerAttributionId(ChannelInterface $channel): string;

    public function getPayPalPaymentMethod(ChannelInterface $channel): PaymentMethodInterface;
}
