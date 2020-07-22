<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;

interface OnboardedPayPalClientIdProviderInterface
{
    public function getForChannel(ChannelInterface $channel): string;
}
