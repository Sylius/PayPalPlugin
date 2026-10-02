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

namespace Sylius\PayPalPlugin\Onboarding\Resolver;

use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Api\OnboardingTokenApiInterface;
use Sylius\PayPalPlugin\Api\SellerCredentialsApiInterface;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;

final readonly class SellerOnboardingResolver implements SellerOnboardingResolverInterface
{
    public function __construct(
        private OnboardingTokenApiInterface $onboardingTokenApi,
        private SellerCredentialsApiInterface $sellerCredentialsApi,
        private AuthorizeClientApiInterface $authorizeClientApi,
        private MerchantOnboardingStatusApiInterface $merchantOnboardingStatusApi,
        private PartnerCredentialsProviderInterface $partnerCredentialsProvider,
    ) {
    }

    public function resolve(string $authCode, string $sharedId, string $sellerNonce): SellerOnboardingResult
    {
        $partnerId = $this->partnerCredentialsProvider->provide()->getPartnerId();

        $onboardingToken = $this->onboardingTokenApi->getFromAuthorizationCode($sharedId, $authCode, $sellerNonce);

        $credentials = $this->sellerCredentialsApi->get($onboardingToken, $partnerId);

        $sellerToken = $this->authorizeClientApi->authorize($credentials['client_id'], $credentials['client_secret']);

        $status = $this->merchantOnboardingStatusApi->get($sellerToken, $partnerId, $credentials['payer_id']);

        return new SellerOnboardingResult(
            $credentials['client_id'],
            $credentials['client_secret'],
            $credentials['payer_id'],
            $status,
        );
    }
}
