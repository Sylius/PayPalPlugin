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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;

/** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
final readonly class ExpressCheckoutChecker implements ExpressCheckoutCheckerInterface
{
    public function __construct(
        private ?PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider,
    ) {
    }

    public function isEnabled(OrderInterface $order): bool
    {
        /** @var ChannelInterface|null $channel */
        $channel = $order->getChannel();
        if (null === $channel || null === $this->fundingSourcesConfigurationProvider) {
            return true;
        }

        try {
            return $this->fundingSourcesConfigurationProvider->isExpressCheckoutEnabled($channel);
        } catch (\InvalidArgumentException) {
            return true;
        }
    }
}
