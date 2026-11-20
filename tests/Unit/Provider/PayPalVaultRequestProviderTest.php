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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Checker\VaultingCapabilityCheckerInterface;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Model\PayPalVaultRequirement;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProvider;
use Sylius\PayPalPlugin\Resolver\NullVaultRequestResolver;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;

final class PayPalVaultRequestProviderTest extends TestCase
{
    public function test_it_provides_a_met_requirement_with_the_vault_request_the_resolver_gives_for_a_merchant_that_can_vault(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);

        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with($order)->willReturn($vaultRequest);

        self::assertEquals(
            PayPalVaultRequirement::met($vaultRequest),
            $this->provider($resolver, $this->createStub(VaultingCapabilityCheckerInterface::class))->provide($order, $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true])),
        );
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function payPalConfigsWithoutVaulting(): iterable
    {
        yield 'merchant without the vaulting capability' => [['vaulting_enabled' => false]];
        yield 'merchant onboarded before vaulting' => [[]];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('payPalConfigsWithoutVaulting')]
    public function test_it_provides_an_unmet_requirement_when_the_order_needs_vaulting_and_paypal_still_cannot_vault_for_the_merchant(array $config): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal', $config);

        $vaultingCapabilityChecker = $this->createMock(VaultingCapabilityCheckerInterface::class);
        $vaultingCapabilityChecker->expects(self::once())->method('isVaultingActive')->with($paymentMethod)->willReturn(false);

        self::assertEquals(
            PayPalVaultRequirement::unmet(),
            $this->provider($this->vaultingResolver(), $vaultingCapabilityChecker)->provide($this->createStub(OrderInterface::class), $paymentMethod),
        );
    }

    public function test_it_provides_a_met_requirement_once_paypal_activates_vaulting_for_the_merchant(): void
    {
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);
        $resolver = $this->createStub(VaultRequestResolverInterface::class);
        $resolver->method('resolve')->willReturn($vaultRequest);

        $vaultingCapabilityChecker = $this->createStub(VaultingCapabilityCheckerInterface::class);
        $vaultingCapabilityChecker->method('isVaultingActive')->willReturn(true);

        self::assertEquals(
            PayPalVaultRequirement::met($vaultRequest),
            $this->provider($resolver, $vaultingCapabilityChecker)->provide($this->createStub(OrderInterface::class), $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => false])),
        );
    }

    public function test_it_does_not_ask_paypal_whether_a_merchant_that_can_vault_can_vault(): void
    {
        $vaultingCapabilityChecker = $this->createMock(VaultingCapabilityCheckerInterface::class);
        $vaultingCapabilityChecker->expects(self::never())->method('isVaultingActive');

        self::assertTrue($this->provider($this->vaultingResolver(), $vaultingCapabilityChecker)->provide($this->createStub(OrderInterface::class), $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => true]))->isMet());
    }

    public function test_it_provides_no_requirement_for_an_order_that_needs_no_vaulting_without_asking_paypal(): void
    {
        $vaultingCapabilityChecker = $this->createMock(VaultingCapabilityCheckerInterface::class);
        $vaultingCapabilityChecker->expects(self::never())->method('isVaultingActive');

        self::assertEquals(
            PayPalVaultRequirement::notRequired(),
            $this->provider(new NullVaultRequestResolver(), $vaultingCapabilityChecker)->provide($this->createStub(OrderInterface::class), $this->paymentMethod('sylius_paypal', ['vaulting_enabled' => false])),
        );
    }

    public function test_it_provides_no_requirement_through_a_gateway_that_is_not_paypal(): void
    {
        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        self::assertEquals(
            PayPalVaultRequirement::notRequired(),
            $this->provider($resolver, $this->createStub(VaultingCapabilityCheckerInterface::class))->provide($this->createStub(OrderInterface::class), $this->paymentMethod('offline', ['vaulting_enabled' => true])),
        );
    }

    public function test_it_provides_no_requirement_without_a_payment_method(): void
    {
        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        self::assertEquals(
            PayPalVaultRequirement::notRequired(),
            $this->provider($resolver, $this->createStub(VaultingCapabilityCheckerInterface::class))->provide($this->createStub(OrderInterface::class), null),
        );
    }

    private function provider(VaultRequestResolverInterface $resolver, VaultingCapabilityCheckerInterface $vaultingCapabilityChecker): PayPalVaultRequestProvider
    {
        return new PayPalVaultRequestProvider($resolver, $vaultingCapabilityChecker);
    }

    private function vaultingResolver(): VaultRequestResolverInterface
    {
        $resolver = $this->createStub(VaultRequestResolverInterface::class);
        $resolver->method('resolve')->willReturn(new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID));

        return $resolver;
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
