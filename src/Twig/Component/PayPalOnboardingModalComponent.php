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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Exception\MerchantIntegrationStatusUnavailableException;
use Sylius\PayPalPlugin\Model\PermissionsVerification;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Verifier\PermissionsVerifierInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('sylius_paypal_onboarding_modal', template: '@SyliusPayPalPlugin/admin/shared/components/paypal_onboarding_modal.html.twig')]
final class PayPalOnboardingModalComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?string $modalId = null;

    #[LiveProp]
    public ?string $type = null;

    #[LiveProp]
    public string $onboardingUrl = '';

    #[LiveProp]
    public bool $loading = true;

    #[LiveProp]
    public bool $failed = false;

    #[LiveProp]
    public bool $opened = false;

    #[LiveProp]
    public bool $sandbox = false;

    #[LiveProp]
    public ?int $paymentMethodId = null;

    public ?PermissionsVerification $verification = null;

    public bool $verificationFailed = false;

    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private readonly PayPalOnboardingUrlProviderInterface $onboardingUrlProvider,
        private readonly SellerNonceManagerInterface $sellerNonceManager,
        private readonly LoggerInterface $logger,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PermissionsVerifierInterface $permissionsVerifier,
    ) {
    }

    #[LiveAction]
    public function loadOnboardingUrl(): void
    {
        $this->opened = true;

        if (null !== $this->paymentMethodId && !$this->requiresConsent($this->paymentMethodId)) {
            $this->loading = false;

            return;
        }

        try {
            $this->onboardingUrl = $this->onboardingUrlProvider->generate(
                $this->sellerNonceManager->generate($this->sandbox),
                $this->sandbox,
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Could not generate the PayPal onboarding URL: %s', $exception->getMessage()),
            );
            $this->failed = true;
        }

        $this->loading = false;
    }

    private function requiresConsent(int $paymentMethodId): bool
    {
        $paymentMethod = $this->paymentMethodRepository->find($paymentMethodId);
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            return true;
        }

        try {
            $this->verification = $this->permissionsVerifier->verify($paymentMethod);
        } catch (MerchantIntegrationStatusUnavailableException $exception) {
            $this->logger->warning($exception->getMessage(), ['exception' => $exception]);
            $this->verificationFailed = true;

            return true;
        }

        return $this->verification->isConsentRequired();
    }
}
