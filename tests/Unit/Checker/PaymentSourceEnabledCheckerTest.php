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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Checker\PaymentSourceEnabledChecker;
use Sylius\PayPalPlugin\Checker\PaymentSourceEnabledCheckerInterface;

final class PaymentSourceEnabledCheckerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function toggledPaymentSources(): iterable
    {
        yield 'Venmo' => ['venmo', 'venmo_enabled'];
        yield 'Google Pay' => ['google_pay', 'google_pay_enabled'];
        yield 'Apple Pay' => ['apple_pay', 'apple_pay_enabled'];
        yield 'Trustly' => ['trustly', 'trustly_enabled'];
    }

    public function test_it_implements_payment_source_enabled_checker_interface(): void
    {
        self::assertInstanceOf(PaymentSourceEnabledCheckerInterface::class, new PaymentSourceEnabledChecker());
    }

    #[DataProvider('toggledPaymentSources')]
    public function test_it_follows_the_toggle_of_the_payment_method_it_is_given(string $paymentSource, string $toggle): void
    {
        $checker = new PaymentSourceEnabledChecker();

        self::assertTrue($checker->isEnabled($paymentSource, $this->paymentMethodConfiguredWith([$toggle => true])));
        self::assertFalse($checker->isEnabled($paymentSource, $this->paymentMethodConfiguredWith([$toggle => false])));
        self::assertFalse($checker->isEnabled($paymentSource, $this->paymentMethodConfiguredWith([])));
    }

    public function test_it_always_offers_the_paypal_wallet_and_cards(): void
    {
        $checker = new PaymentSourceEnabledChecker();
        $paymentMethod = $this->paymentMethodConfiguredWith([]);

        self::assertTrue($checker->isEnabled('paypal', $paymentMethod));
        self::assertTrue($checker->isEnabled('card', $paymentMethod));
    }

    /** @param array<string, mixed> $config */
    private function paymentMethodConfiguredWith(array $config): PaymentMethodInterface
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn($config);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
