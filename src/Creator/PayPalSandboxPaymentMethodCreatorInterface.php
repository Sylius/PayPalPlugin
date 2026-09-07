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

namespace Sylius\PayPalPlugin\Creator;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PayPalSandboxPaymentMethodCreatorInterface
{
    public const GATEWAY_NAME = 'sylius_paypal_sandbox';

    /**
     * The BN code PayPal assigned to this integration (SDD v1.5 §1.1.3), sent as the
     * PayPal-Partner-Attribution-Id header on every API call.
     *
     * This constant only seeds payment methods created through the sandbox flow. A production
     * merchant's value comes from the facilitator's onboarding response
     * (Onboarding\Processor\BasicOnboardingProcessor), and existing payment methods keep whatever
     * their gateway config already holds - so changing it here does not retroactively move their volume.
     */
    public const PARTNER_ATTRIBUTION_ID = 'Sylius_MP_PPCP';

    public const PAYMENT_METHOD_CODE = 'PAYPAL';

    public const PAYMENT_METHOD_NAME = 'PayPal';

    public const PAYMENT_METHOD_DESCRIPTION = 'Pay with PayPal';

    public const SYLIUS_SANDBOX_MERCHANT_ID = 'SYLIUS_SANDBOX_MERCHANT_ID';

    public function create(string $clientId, string $clientSecret, string $merchantId): PaymentMethodInterface;
}
