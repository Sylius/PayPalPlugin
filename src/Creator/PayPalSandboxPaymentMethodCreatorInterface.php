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
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;

interface PayPalSandboxPaymentMethodCreatorInterface
{
    public const GATEWAY_NAME = 'sylius_paypal_sandbox';

    /** @deprecated since 2.1, use SyliusPayPalExtension::PARTNER_ATTRIBUTION_ID instead */
    public const PARTNER_ATTRIBUTION_ID = SyliusPayPalExtension::PARTNER_ATTRIBUTION_ID;

    public const PAYMENT_METHOD_CODE = 'PAYPAL';

    public const PAYMENT_METHOD_NAME = 'PayPal';

    public const PAYMENT_METHOD_DESCRIPTION = 'Pay with PayPal';

    public const SYLIUS_SANDBOX_MERCHANT_ID = 'SYLIUS_SANDBOX_MERCHANT_ID';

    public function create(string $clientId, string $clientSecret, string $merchantId): PaymentMethodInterface;
}
