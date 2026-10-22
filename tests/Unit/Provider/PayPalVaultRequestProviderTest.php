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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProvider;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;

final class PayPalVaultRequestProviderTest extends TestCase
{
    public function test_it_provides_the_vault_request_the_resolver_gives_for_a_merchant_that_can_vault(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);

        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with($order)->willReturn($vaultRequest);

        self::assertSame($vaultRequest, (new PayPalVaultRequestProvider($resolver, $this->createStub(PayPalPaymentMethodsResolverInterface::class)))->provide($order, $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true])));
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function gatewaysThatCannotVault(): iterable
    {
        yield 'merchant without the vaulting capability' => ['sylius_paypal', ['vaulting_enabled' => false]];
        yield 'merchant onboarded before vaulting' => ['sylius_paypal', []];
        yield 'gateway that is not paypal' => ['offline', ['vaulting_enabled' => true]];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('gatewaysThatCannotVault')]
    public function test_it_provides_no_vault_request_through_a_gateway_that_cannot_vault(string $factoryName, array $config): void
    {
        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        self::assertNull((new PayPalVaultRequestProvider($resolver, $this->createStub(PayPalPaymentMethodsResolverInterface::class)))->provide($this->createStub(OrderInterface::class), $this->paymentMethod($factoryName, $config)));
    }

    public function test_it_provides_no_vault_request_without_a_payment_method(): void
    {
        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        self::assertNull((new PayPalVaultRequestProvider($resolver, $this->createStub(PayPalPaymentMethodsResolverInterface::class)))->provide($this->createStub(OrderInterface::class), null));
    }

    public function test_it_checks_the_cart_against_the_paypal_method_its_payment_already_uses(): void
    {
        $vaultingMethod = $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true]);
        $order = $this->cartWith($vaultingMethod);
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);

        $resolver = $this->createStub(VaultRequestResolverInterface::class);
        $resolver->method('resolve')->willReturn($vaultRequest);

        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->willReturn([$this->paymentMethod('sylius_paypal', ['vaulting_enabled' => false])]);

        self::assertSame($vaultRequest, (new PayPalVaultRequestProvider($resolver, $payPalMethodsResolver))->provideForCart($order));
    }

    public function test_it_checks_the_cart_paid_otherwise_against_the_first_paypal_method_of_its_channel(): void
    {
        $order = $this->cartWith($this->paymentMethod('offline', []));
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);

        $resolver = $this->createStub(VaultRequestResolverInterface::class);
        $resolver->method('resolve')->willReturn($vaultRequest);

        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->willReturn([$this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true])]);

        self::assertSame($vaultRequest, (new PayPalVaultRequestProvider($resolver, $payPalMethodsResolver))->provideForCart($order));
    }

    private function cartWith(PaymentMethodInterface $paymentMethod): OrderInterface
    {
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->createStub(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);
        $order->method('getChannel')->willReturn($this->createStub(ChannelInterface::class));

        return $order;
    }

    /** @param array<string, mixed> $config */
    private function paymentMethod(string $factoryName, array $config): PaymentMethodInterface
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);
        $gatewayConfig->method('getConfig')->willReturn($config);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
