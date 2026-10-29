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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Provider\EnabledPaymentSourcesProvider;
use Sylius\PayPalPlugin\Provider\EnabledPaymentSourcesProviderInterface;

final class EnabledPaymentSourcesProviderTest extends TestCase
{
    public function test_it_implements_enabled_payment_sources_provider_interface(): void
    {
        self::assertInstanceOf(EnabledPaymentSourcesProviderInterface::class, new EnabledPaymentSourcesProvider());
    }

    public function test_it_provides_nothing_for_a_payment_method_without_gateway_config(): void
    {
        self::assertSame([], (new EnabledPaymentSourcesProvider())->provide(new PaymentMethod()));
    }

    public function test_it_provides_paypal_pay_later_and_cards_by_default(): void
    {
        self::assertSame(
            ['paypal', 'pay_later', 'visa', 'master_card', 'amex'],
            (new EnabledPaymentSourcesProvider())->provide($this->paymentMethod([])),
        );
    }

    public function test_it_provides_every_payment_source_enabled_in_the_configuration(): void
    {
        self::assertSame(
            ['paypal', 'pay_later', 'venmo', 'apple_pay', 'google_pay', 'trustly', 'visa', 'master_card', 'amex'],
            (new EnabledPaymentSourcesProvider())->provide($this->paymentMethod([
                'pay_later_enabled' => true,
                'venmo_enabled' => true,
                'apple_pay_enabled' => true,
                'google_pay_enabled' => true,
                'trustly_enabled' => true,
            ])),
        );
    }

    public function test_it_skips_payment_sources_disabled_in_the_configuration(): void
    {
        self::assertSame(
            ['paypal', 'visa', 'master_card', 'amex'],
            (new EnabledPaymentSourcesProvider())->provide($this->paymentMethod([
                'pay_later_enabled' => false,
                'venmo_enabled' => false,
                'apple_pay_enabled' => false,
                'google_pay_enabled' => false,
                'trustly_enabled' => false,
            ])),
        );
    }

    /** @param array<string, mixed> $config */
    private function paymentMethod(array $config): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig($config);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
