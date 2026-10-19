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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface as PayumGatewayConfigInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationChecker;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;

final class PaymentAuthorizationCheckerTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function authorizablePaymentSources(): iterable
    {
        yield 'PayPal and Pay Later' => ['paypal'];
        yield 'Venmo' => ['venmo'];
        yield 'cards' => ['card'];
        yield 'Apple Pay' => ['apple_pay'];
        yield 'Google Pay' => ['google_pay'];
    }

    public function test_it_implements_payment_authorization_checker_interface(): void
    {
        self::assertInstanceOf(PaymentAuthorizationCheckerInterface::class, new PaymentAuthorizationChecker());
    }

    public function test_it_authorizes_the_payments_of_a_payment_method_set_to_authorize(): void
    {
        $checker = new PaymentAuthorizationChecker();

        self::assertTrue($checker->authorizesPayments($this->paymentMethodConfiguredWith(['use_authorize' => true])));
        self::assertFalse($checker->authorizesPayments($this->paymentMethodConfiguredWith(['use_authorize' => false])));
        self::assertFalse($checker->authorizesPayments($this->paymentMethodConfiguredWith([])));
    }

    public function test_it_does_not_authorize_on_a_payum_gateway_that_uses_the_flag_to_postpone_the_order(): void
    {
        $gatewayConfig = $this->createMock(PayumGatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['use_authorize' => true]);
        $gatewayConfig->method('getUsePayum')->willReturn(true);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        self::assertFalse((new PaymentAuthorizationChecker())->authorizesPayments($paymentMethod));
    }

    public function test_it_does_not_authorize_without_a_gateway_config(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        self::assertFalse((new PaymentAuthorizationChecker())->authorizesPayments($paymentMethod));
    }

    #[DataProvider('authorizablePaymentSources')]
    public function test_it_authorizes_the_payment_sources_paypal_can_authorize(string $paymentSource): void
    {
        $checker = new PaymentAuthorizationChecker();

        self::assertTrue($checker->authorizes($this->paymentMethodConfiguredWith(['use_authorize' => true]), $paymentSource));
        self::assertFalse($checker->authorizes($this->paymentMethodConfiguredWith([]), $paymentSource));
    }

    public function test_it_does_not_authorize_a_redirect_payment_source(): void
    {
        self::assertFalse((new PaymentAuthorizationChecker())->authorizes(
            $this->paymentMethodConfiguredWith(['use_authorize' => true]),
            'trustly',
        ));
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
