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
use Sylius\PayPalPlugin\Exception\MerchantIntegrationStatusUnavailableException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PermissionsVerification;

interface PermissionsVerifierInterface
{
    /**
     * Requires the consent without asking PayPal when no onboarding scope is stored in the payment method or it misses
     * the product, a secondary product or a feature of the current one, as PayPal does not report them. Otherwise reads
     * the merchant integration status from PayPal and verifies it through verifyStatus().
     *
     * @throws MerchantIntegrationStatusUnavailableException
     */
    public function verify(PaymentMethodInterface $paymentMethod): PermissionsVerification;

    /** Stores the current onboarding scope in the payment method when the status has every capability of it active. */
    public function verifyStatus(PaymentMethodInterface $paymentMethod, OnboardingStatus $status): PermissionsVerification;
}
