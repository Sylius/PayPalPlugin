<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Initiator;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Onboarding\Api\PartnerReferralsApiInterface;
use Sylius\PayPalPlugin\Provider\ApiTokenProviderInterface;

final class SandboxOnboardingInitiator implements OnboardingInitiatorInterface
{
    /** @var ApiTokenProviderInterface */
    private $payPayApiTokenProvider;

    /** @var PartnerReferralsApiInterface */
    private $partnerReferralsApi;

    public function __construct(
        ApiTokenProviderInterface $payPayApiTokenProvider,
        PartnerReferralsApiInterface $partnerReferralsApi
    ) {
        $this->payPayApiTokenProvider = $payPayApiTokenProvider;
        $this->partnerReferralsApi = $partnerReferralsApi;
    }

    public function initiate(PaymentMethodInterface $paymentMethod): string
    {
        if (!$this->supports($paymentMethod)) {
            throw new \DomainException('not supported'); // TODO: Lol, improve this message
        }

        $accessToken = $this->payPayApiTokenProvider->getToken();

        return $this->partnerReferralsApi->create($accessToken);
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool // TODO: Design smell - it looks like this function will be the same no matter the implementation
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig === null) {
            return false;
        }

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return false;
        }

        if (isset($gatewayConfig->getConfig()['merchant_id'])) {
            return false;
        }

        return true;
    }
}
