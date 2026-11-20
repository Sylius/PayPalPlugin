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

namespace Sylius\PayPalPlugin\Verifier;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PermissionsVerification;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;
use Sylius\PayPalPlugin\Updater\OnboardingScopeUpdaterInterface;

final readonly class PermissionsVerifier implements PermissionsVerifierInterface
{
    public function __construct(
        private MerchantIntegrationStatusProviderInterface $merchantIntegrationStatusProvider,
        private OnboardingScopeUpdaterInterface $onboardingScopeUpdater,
    ) {
    }

    public function verify(PaymentMethodInterface $paymentMethod): PermissionsVerification
    {
        $status = $this->merchantIntegrationStatusProvider->provide($paymentMethod);
        if (null === $status) {
            return PermissionsVerification::consentRequired();
        }

        return $this->verifyStatus($paymentMethod, $status);
    }

    public function verifyStatus(PaymentMethodInterface $paymentMethod, OnboardingStatus $status): PermissionsVerification
    {
        $verification = PermissionsVerification::of($status, OnboardingScope::current());
        if ($verification->isUpToDate()) {
            $this->onboardingScopeUpdater->update($paymentMethod);
        }

        return $verification;
    }
}
