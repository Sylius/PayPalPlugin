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
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Sylius\PayPalPlugin\Resolver\NullVaultRequestResolver;
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

        self::assertSame($vaultRequest, (new PayPalVaultRequestProvider($resolver, $this->createStub(PayPalPaymentMethodsResolverInterface::class), $this->createStub(VaultingCapabilityRefresherInterface::class)))->provide($order, $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true])));
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

        self::assertNull((new PayPalVaultRequestProvider($resolver, $this->createStub(PayPalPaymentMethodsResolverInterface::class), $this->createStub(VaultingCapabilityRefresherInterface::class)))->provide($this->createStub(OrderInterface::class), $this->paymentMethod($factoryName, $config)));
    }

    public function test_it_provides_no_vault_request_without_a_payment_method(): void
    {
        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        self::assertNull((new PayPalVaultRequestProvider($resolver, $this->createStub(PayPalPaymentMethodsResolverInterface::class), $this->createStub(VaultingCapabilityRefresherInterface::class)))->provide($this->createStub(OrderInterface::class), null));
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

        self::assertSame($vaultRequest, (new PayPalVaultRequestProvider($resolver, $payPalMethodsResolver, $this->createStub(VaultingCapabilityRefresherInterface::class)))->provideForCart($order));
    }

    public function test_it_checks_the_cart_paid_otherwise_against_the_first_paypal_method_of_its_channel(): void
    {
        $order = $this->cartWith($this->paymentMethod('offline', []));
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);

        $resolver = $this->createStub(VaultRequestResolverInterface::class);
        $resolver->method('resolve')->willReturn($vaultRequest);

        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->willReturn([$this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true])]);

        self::assertSame($vaultRequest, (new PayPalVaultRequestProvider($resolver, $payPalMethodsResolver, $this->createStub(VaultingCapabilityRefresherInterface::class)))->provideForCart($order));
    }

    public function test_it_tells_a_channel_vaults_when_any_of_its_paypal_methods_can_vault(): void
    {
        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->willReturn([
            $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => false]),
            $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true]),
        ]);

        $resolver = $this->createStub(VaultRequestResolverInterface::class);
        $resolver->method('vaultsInChannel')->willReturn(true);

        self::assertTrue((new PayPalVaultRequestProvider($resolver, $payPalMethodsResolver, $this->createStub(VaultingCapabilityRefresherInterface::class)))->isVaultingEnabledInChannel($this->createStub(ChannelInterface::class)));
    }

    public function test_it_tells_a_channel_no_integration_vaults_in_does_not_vault(): void
    {
        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->willReturn([$this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true])]);

        self::assertFalse((new PayPalVaultRequestProvider(new NullVaultRequestResolver(), $payPalMethodsResolver, $this->createStub(VaultingCapabilityRefresherInterface::class)))->isVaultingEnabledInChannel($this->createStub(ChannelInterface::class)));
    }

    public function test_it_tells_a_channel_without_a_paypal_method_that_can_vault_does_not_vault(): void
    {
        $payPalMethodsResolver = $this->createStub(PayPalPaymentMethodsResolverInterface::class);
        $payPalMethodsResolver->method('getInChannel')->willReturn([$this->paymentMethod('sylius_paypal', ['vaulting_enabled' => false])]);

        $resolver = $this->createStub(VaultRequestResolverInterface::class);
        $resolver->method('vaultsInChannel')->willReturn(true);

        self::assertFalse((new PayPalVaultRequestProvider($resolver, $payPalMethodsResolver, $this->createStub(VaultingCapabilityRefresherInterface::class)))->isVaultingEnabledInChannel($this->createStub(ChannelInterface::class)));
    }

    public function test_it_asks_paypal_again_whether_a_method_can_vault_once_an_integration_vaults_in_its_channel(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => false]);
        $order = $this->cartWith($paymentMethod);
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);

        $resolver = $this->createStub(VaultRequestResolverInterface::class);
        $resolver->method('vaultsInChannel')->willReturn(true);
        $resolver->method('resolve')->willReturn($vaultRequest);

        $refresher = $this->createMock(VaultingCapabilityRefresherInterface::class);
        $refresher->expects(self::once())->method('refresh')->with($paymentMethod)->willReturn(true);

        self::assertSame($vaultRequest, (new PayPalVaultRequestProvider($resolver, $this->createStub(PayPalPaymentMethodsResolverInterface::class), $refresher))->provide($order, $paymentMethod));
    }

    public function test_it_does_not_ask_paypal_whether_a_method_can_vault_when_no_integration_vaults_in_its_channel(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => false]);

        $refresher = $this->createMock(VaultingCapabilityRefresherInterface::class);
        $refresher->expects(self::never())->method('refresh');

        self::assertNull((new PayPalVaultRequestProvider(new NullVaultRequestResolver(), $this->createStub(PayPalPaymentMethodsResolverInterface::class), $refresher))->provide($this->cartWith($paymentMethod), $paymentMethod));
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
