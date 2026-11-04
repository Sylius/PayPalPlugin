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
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class MerchantIntegrationStatusProvider implements MerchantIntegrationStatusProviderInterface
{
    public function __construct(
        private AuthorizeClientApiInterface $authorizeClientApi,
        private MerchantOnboardingStatusApiInterface $merchantOnboardingStatusApi,
        private PartnerCredentialsProviderInterface $partnerCredentialsProvider,
        private LoggerInterface $logger,
    ) {
    }

    public function provide(PayPalGatewayConfig $config): ?OnboardingStatus
    {
        if (!$config->hasClientId()) {
            return null;
        }

        $sandbox = $config->isSandbox();

        try {
            $sellerToken = $this->authorizeClientApi->authorize($config->clientId(), $config->clientSecret(), $sandbox)->value();
        } catch (PayPalApiException|PayPalPluginException|ClientExceptionInterface|\JsonException|\InvalidArgumentException $exception) {
            $this->logger->warning('Could not read the PayPal merchant integration status: {message}', ['message' => $exception->getMessage()]);

            return null;
        }

        foreach ($this->partnerIds($config, $sandbox) as $partnerId) {
            try {
                return $this->merchantOnboardingStatusApi->get($sellerToken, $partnerId, $config->merchantId(), $sandbox);
            } catch (PayPalApiException|PayPalPluginException|ClientExceptionInterface|\JsonException|\InvalidArgumentException $exception) {
                $this->logger->warning('Could not read the PayPal merchant integration status for partner {partner_id}: {message}', [
                    'partner_id' => $partnerId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    /** @return list<string> */
    private function partnerIds(PayPalGatewayConfig $config, bool $sandbox): array
    {
        $partnerIds = [];

        $facilitatorPartnerId = $config->facilitatorPartnerId();
        if (null !== $facilitatorPartnerId && '' !== $facilitatorPartnerId) {
            $partnerIds[] = $facilitatorPartnerId;
        }

        try {
            $partnerIds[] = $this->partnerCredentialsProvider->provide($sandbox)->getPartnerId();
        } catch (PayPalPluginException) {
        }

        return array_values(array_unique($partnerIds));
    }
}
