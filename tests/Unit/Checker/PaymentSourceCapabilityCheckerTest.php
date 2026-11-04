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

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Checker\PaymentSourceCapabilityChecker;
use Sylius\PayPalPlugin\Checker\PaymentSourceCapabilityCheckerInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;

final class PaymentSourceCapabilityCheckerTest extends TestCase
{
    public function test_it_implements_the_payment_source_capability_checker_interface(): void
    {
        self::assertInstanceOf(PaymentSourceCapabilityCheckerInterface::class, $this->checker(null));
    }

    public function test_it_tells_whether_the_paypal_account_has_the_capability_apple_pay_and_google_pay_need(): void
    {
        $checker = $this->checker(new OnboardingStatus(true, true, ['APPLE_PAY']));

        self::assertTrue($checker->isAvailable('apple_pay', $this->config()));
        self::assertFalse($checker->isAvailable('google_pay', $this->config()));
    }

    public function test_it_does_not_ask_paypal_about_a_payment_source_that_needs_no_capability(): void
    {
        $merchantIntegrationStatusProvider = $this->createMock(MerchantIntegrationStatusProviderInterface::class);
        $merchantIntegrationStatusProvider->expects(self::never())->method('provide');

        $checker = new PaymentSourceCapabilityChecker($merchantIntegrationStatusProvider);

        self::assertTrue($checker->isAvailable('paypal', $this->config()));
        self::assertTrue($checker->isAvailable('venmo', $this->config()));
    }

    public function test_it_hides_nothing_when_the_status_of_the_paypal_account_is_unknown(): void
    {
        $checker = $this->checker(null);

        self::assertTrue($checker->isAvailable('apple_pay', $this->config()));
        self::assertTrue($checker->isAvailable('google_pay', $this->config()));
    }

    private function checker(?OnboardingStatus $status): PaymentSourceCapabilityChecker
    {
        $merchantIntegrationStatusProvider = $this->createStub(MerchantIntegrationStatusProviderInterface::class);
        $merchantIntegrationStatusProvider->method('provide')->willReturn($status);

        return new PaymentSourceCapabilityChecker($merchantIntegrationStatusProvider);
    }

    private function config(): PayPalGatewayConfig
    {
        return PayPalGatewayConfig::fromArray(['client_id' => 'CLIENT-ID', 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT-ID']);
    }
}
