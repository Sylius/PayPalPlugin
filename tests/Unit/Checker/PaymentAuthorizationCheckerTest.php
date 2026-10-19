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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface as PayumGatewayConfigInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationChecker;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;

final class PaymentAuthorizationCheckerTest extends TestCase
{
    public function test_it_implements_payment_authorization_checker_interface(): void
    {
        self::assertInstanceOf(PaymentAuthorizationCheckerInterface::class, $this->checker());
    }

    public function test_it_authorizes_the_payments_of_a_payment_method_set_to_authorize(): void
    {
        $checker = $this->checker();

        self::assertTrue($checker->isAuthorizeEnabled($this->paymentMethodConfiguredWith(['use_authorize' => true])));
        self::assertFalse($checker->isAuthorizeEnabled($this->paymentMethodConfiguredWith(['use_authorize' => false])));
        self::assertFalse($checker->isAuthorizeEnabled($this->paymentMethodConfiguredWith([])));
    }

    public function test_it_does_not_authorize_on_a_payum_gateway_that_uses_the_flag_to_postpone_the_order(): void
    {
        $gatewayConfig = $this->createMock(PayumGatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['use_authorize' => true]);
        $gatewayConfig->method('getUsePayum')->willReturn(true);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        self::assertFalse($this->checker()->isAuthorizeEnabled($paymentMethod));
    }

    public function test_it_does_not_authorize_without_a_gateway_config(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        self::assertFalse($this->checker()->isAuthorizeEnabled($paymentMethod));
    }

    public function test_it_authorizes_only_the_payment_sources_it_is_configured_with(): void
    {
        $checker = $this->checker();
        $paymentMethod = $this->paymentMethodConfiguredWith(['use_authorize' => true]);

        self::assertTrue($checker->isAuthorizable('paypal', $paymentMethod));
        self::assertTrue($checker->isAuthorizable('card', $paymentMethod));
        self::assertFalse($checker->isAuthorizable('trustly', $paymentMethod));
    }

    public function test_it_does_not_authorize_a_configured_payment_source_when_the_payment_method_captures(): void
    {
        self::assertFalse($this->checker()->isAuthorizable('paypal', $this->paymentMethodConfiguredWith([])));
    }

    private function checker(): PaymentAuthorizationChecker
    {
        return new PaymentAuthorizationChecker(['paypal', 'card']);
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
