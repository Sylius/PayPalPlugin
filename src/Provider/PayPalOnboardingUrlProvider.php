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

use Sylius\PayPalPlugin\UrlUtils;

final readonly class PayPalOnboardingUrlProvider implements PayPalOnboardingUrlProviderInterface
{
    private string $onboardingUrl;

    public function __construct(
        string $webUrl,
        private PartnerCredentialsProviderInterface $partnerCredentialsProvider,
    ) {
        $this->onboardingUrl = $webUrl . '/bizsignup/partner/entry';
    }

    public function generate(string $sellerNonce): string
    {
        $partnerCredentials = $this->partnerCredentialsProvider->provide();

        return UrlUtils::appendQueryString(
            $this->onboardingUrl,
            http_build_query([
                'partnerId' => $partnerCredentials->getPartnerId(),
                'product' => 'express_checkout',
                'integrationType' => 'FO',
                'features' => 'payment,refund,access_merchant_information',
                'partnerClientId' => $partnerCredentials->getPartnerClientId(),
                'partnerLogoUrl' => $partnerCredentials->getPartnerLogoUrl(),
                'displayMode' => 'minibrowser',
                'sellerNonce' => $sellerNonce,
            ]),
        );
    }
}
