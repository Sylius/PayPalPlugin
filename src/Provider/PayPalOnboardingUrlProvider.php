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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalOnboardingUrlProvider implements PayPalOnboardingUrlProviderInterface
{
    public function __construct(
        private HostProviderInterface $hostProvider,
        private PartnerCredentialsProviderInterface $partnerCredentialsProvider,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function generate(string $sellerNonce, bool $sandbox): string
    {
        $partnerCredentials = $this->partnerCredentialsProvider->provide($sandbox);

        return UrlUtils::appendQueryString(
            $this->hostProvider->getWebUrlForMode($sandbox) . '/bizsignup/partner/entry',
            http_build_query([
                'partnerId' => $partnerCredentials->getPartnerId(),
                'product' => 'ppcp',
                'secondaryProducts' => 'payment_methods,advanced_vaulting',
                'capabilities' => 'APPLE_PAY,GOOGLE_PAY,PAYPAL_WALLET_VAULTING_ADVANCED',
                'integrationType' => 'FO',
                'features' => 'payment,refund,access_merchant_information,billing_agreement,vault',
                'partnerClientId' => $partnerCredentials->getPartnerClientId(),
                'partnerLogoUrl' => $partnerCredentials->getPartnerLogoUrl(),
                'displayMode' => 'minibrowser',
                'sellerNonce' => $sellerNonce,
                'returnToPartnerUrl' => $this->urlGenerator->generate(
                    'sylius_admin_payment_method_index',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ]),
        );
    }
}
