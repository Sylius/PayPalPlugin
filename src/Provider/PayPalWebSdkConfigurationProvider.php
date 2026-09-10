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

final readonly class PayPalWebSdkConfigurationProvider implements PayPalWebSdkConfigurationProviderInterface
{
    public function __construct(
        private PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        private PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider,
        private string $webUrl,
        private bool $sandbox,
        private ?string $testBuyerCountry,
    ) {
    }

    public function getScriptUrl(): string
    {
        return sprintf('%s/web-sdk/v6/core', $this->webUrl);
    }

    public function getInstanceConfig(ChannelInterface $channel, string $pageType): array
    {
        $components = ['paypal-payments'];
        if ($this->fundingSourcesConfigurationProvider->isVenmoEnabled($channel)) {
            $components[] = 'venmo-payments';
        }

        $config = [
            'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
            'components' => $components,
            'pageType' => $pageType,
            'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
        ];

        // Only ever simulate a buyer location in sandbox - PayPal support: this must never be sent in
        // production, so the sandbox flag is a hard gate, not just a default.
        if ($this->sandbox && $this->testBuyerCountry !== null) {
            $config['testBuyerCountry'] = $this->testBuyerCountry;
        }

        return $config;
    }
}
