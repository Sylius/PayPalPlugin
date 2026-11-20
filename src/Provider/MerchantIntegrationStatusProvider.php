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

use Psr\Http\Client\ClientExceptionInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Exception\MerchantIntegrationStatusUnavailableException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\UntrustedPayPalUrlException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class MerchantIntegrationStatusProvider implements MerchantIntegrationStatusProviderInterface
{
    public function __construct(
        private GenericApiInterface $genericApi,
        private HostProviderInterface $hostProvider,
        private PartnerCredentialsProviderInterface $partnerCredentialsProvider,
    ) {
    }

    public function provide(PaymentMethodInterface $paymentMethod): ?OnboardingStatus
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
        if (!$config->hasClientId()) {
            return null;
        }

        $sandbox = $config->isSandbox();

        try {
            $partnerId = $this->partnerCredentialsProvider->provide($sandbox)->getPartnerId();

            return OnboardingStatus::fromMerchantIntegration($this->genericApi->get(
                $paymentMethod,
                sprintf('%sv1/customer/partners/%s/merchant-integrations/%s', $this->hostProvider->getApiBaseUrlForMode($sandbox), $partnerId, $config->merchantId()),
            ));
        } catch (PayPalApiNotFoundException) {
            return null;
        } catch (PayPalApiException|PayPalPluginException|UntrustedPayPalUrlException|ClientExceptionInterface|\JsonException|\InvalidArgumentException $exception) {
            throw new MerchantIntegrationStatusUnavailableException((string) $paymentMethod->getCode(), $exception);
        }
    }
}
