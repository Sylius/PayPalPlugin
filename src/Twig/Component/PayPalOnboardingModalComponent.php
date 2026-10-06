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

use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('sylius_paypal_onboarding_modal', template: '@SyliusPayPalPlugin/admin/shared/components/paypal_onboarding_modal.html.twig')]
final class PayPalOnboardingModalComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $onboardingUrl = '';

    #[LiveProp]
    public bool $loading = true;

    #[LiveProp]
    public bool $failed = false;

    #[LiveProp]
    public bool $sellerAlreadyOnboarded = false;

    public function __construct(
        private readonly PayPalOnboardingUrlProviderInterface $onboardingUrlProvider,
        private readonly SellerNonceManagerInterface $sellerNonceManager,
        private readonly PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[LiveAction]
    public function loadOnboardingUrl(): void
    {
        if ($this->payPalPaymentMethodProvider->exists()) {
            $this->sellerAlreadyOnboarded = true;
            $this->loading = false;

            return;
        }

        try {
            $this->onboardingUrl = $this->onboardingUrlProvider->generate(
                $this->sellerNonceManager->generate(),
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Could not generate the PayPal onboarding URL: %s', $exception->getMessage()),
            );
            $this->failed = true;
        }

        $this->loading = false;
    }
}
