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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final class RedirectPaymentSourceTest extends TestCase
{
    public function test_it_names_trustly_the_way_the_paypal_order_payload_does(): void
    {
        self::assertSame('trustly', RedirectPaymentSource::Trustly->value);
        self::assertSame(PayPalPaymentSourceProviderInterface::TRUSTLY, RedirectPaymentSource::Trustly->value);
    }

    public function test_it_names_trustly_the_way_the_eligibility_request_does(): void
    {
        self::assertSame('TRUSTLY', RedirectPaymentSource::Trustly->eligibilityCode());
    }

    public function test_it_names_the_gateway_configuration_key_of_each_method(): void
    {
        self::assertSame('trustly_enabled', RedirectPaymentSource::Trustly->configurationKey());
    }

    public function test_it_lists_the_payment_source_of_every_method(): void
    {
        self::assertSame(['trustly'], RedirectPaymentSource::values());
    }

    public function test_it_recognises_a_redirect_payment_source(): void
    {
        self::assertSame(RedirectPaymentSource::Trustly, RedirectPaymentSource::tryFrom('trustly'));
        self::assertNull(RedirectPaymentSource::tryFrom(PayPalPaymentSourceProviderInterface::PAYPAL));
        self::assertNull(RedirectPaymentSource::tryFrom(PayPalPaymentSourceProviderInterface::GOOGLE_PAY));
    }

    public function test_it_points_at_the_mark_paypal_hosts_for_each_method(): void
    {
        self::assertSame(
            'https://www.paypalobjects.com/images/checkout/alternative_payments/paypal_trustly_color.svg',
            RedirectPaymentSource::Trustly->iconUrl(),
        );
    }
}
