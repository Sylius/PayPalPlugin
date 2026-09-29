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

namespace Sylius\PayPalPlugin\Model;

final class PayPalGatewayConfig
{
    public const CLIENT_ID = 'client_id';

    public const CLIENT_SECRET = 'client_secret';

    public const MERCHANT_ID = 'merchant_id';

    public const SYLIUS_MERCHANT_ID = 'sylius_merchant_id';

    public const PARTNER_ATTRIBUTION_ID = 'partner_attribution_id';

    public const USE_AUTHORIZE = 'use_authorize';

    public const ONBOARDING_ID = 'onboarding_id';

    public const WEBHOOK_ID = 'webhook_id';

    public const REPORTS_SFTP_USERNAME = 'reports_sftp_username';

    public const REPORTS_SFTP_PASSWORD = 'reports_sftp_password';

    public const PAY_LATER_ENABLED = 'pay_later_enabled';

    public const MESSAGING_ENABLED = 'messaging_enabled';

    public const GOOGLE_PAY_ENABLED = 'google_pay_enabled';

    public const APPLE_PAY_ENABLED = 'apple_pay_enabled';

    private function __construct()
    {
    }
}
