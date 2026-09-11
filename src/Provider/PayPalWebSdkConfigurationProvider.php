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

    public function getInstanceConfig(
        ChannelInterface $channel,
        string $pageType,
        array $components = self::DEFAULT_COMPONENTS,
        ?string $locale = null,
    ): array {
        if ($this->fundingSourcesConfigurationProvider->isMessagingEnabled($channel)) {
            $components[] = 'paypal-messages';
        }

        $instanceConfig = [
            'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
            'components' => $components,
            'pageType' => $pageType,
            'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
        ];

        if (null !== $locale) {
            $instanceConfig['locale'] = str_replace('_', '-', $locale);
        }

        // Only ever simulate a buyer location in sandbox - PayPal support: this must never be sent in
        // production, so the sandbox flag is a hard gate, not just a default.
        if ($this->sandbox && $this->testBuyerCountry !== null) {
            $instanceConfig['testBuyerCountry'] = $this->testBuyerCountry;
        }

        return $instanceConfig;
    }
}
