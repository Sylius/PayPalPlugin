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

use Sylius\PayPalPlugin\Model\OnboardingScope;
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
        $scope = OnboardingScope::current();

        return UrlUtils::appendQueryString(
            $this->hostProvider->getWebUrlForMode($sandbox) . '/bizsignup/partner/entry',
            http_build_query([
                'partnerId' => $partnerCredentials->getPartnerId(),
                'product' => $scope->product(),
                'secondaryProducts' => implode(',', $scope->secondaryProducts()),
                'capabilities' => implode(',', $scope->capabilities()),
                'integrationType' => 'FO',
                'features' => implode(',', $scope->features()),
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
