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

namespace Sylius\PayPalPlugin\Twig\Component;

use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Provider\SellerNonceProviderInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('sylius_paypal_onboarding_modal', template: '@SyliusPayPalPlugin/admin/shared/components/paypal_onboarding_modal.html.twig')]
final class PayPalOnboardingModalComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $onboardingUrl = '';

    public function __construct(
        private readonly PayPalOnboardingUrlProviderInterface $onboardingUrlProvider,
        private readonly SellerNonceProviderInterface $sellerNonceProvider,
    ) {
    }

    public function mount(): void
    {
        $nonce = $this->sellerNonceProvider->generate();
        $this->onboardingUrl = $this->onboardingUrlProvider->generate($nonce);
    }
}
