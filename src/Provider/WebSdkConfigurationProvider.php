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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class WebSdkConfigurationProvider implements WebSdkConfigurationProviderInterface
{
    public function __construct(
        private HostProviderInterface $hostProvider,
        private ?string $testBuyerCountry,
    ) {
    }

    public function getScriptUrl(PaymentMethodInterface $paymentMethod): string
    {
        return sprintf('%s/web-sdk/v6/core', $this->hostProvider->getWebUrlForMode($this->config($paymentMethod)->isSandbox()));
    }

    public function getInstanceConfig(
        PaymentMethodInterface $paymentMethod,
        string $pageType,
        array $components = self::DEFAULT_COMPONENTS,
        ?string $locale = null,
    ): array {
        $config = $this->config($paymentMethod);
        $instanceConfig = [
            'clientId' => $config->clientId(),
            'components' => $components,
            'pageType' => $pageType,
            'partnerAttributionId' => $config->partnerAttributionId(),
        ];

        if (null !== $locale) {
            $instanceConfig['locale'] = str_replace('_', '-', $locale);
        }

        // Only ever simulate a buyer location in sandbox - PayPal support: this must never be sent in
        // production, so the sandbox flag is a hard gate, not just a default.
        if (null !== $this->testBuyerCountry && $config->isSandbox()) {
            $instanceConfig['testBuyerCountry'] = $this->testBuyerCountry;
        }

        return $instanceConfig;
    }

    private function config(PaymentMethodInterface $paymentMethod): PayPalGatewayConfig
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
    }
}
