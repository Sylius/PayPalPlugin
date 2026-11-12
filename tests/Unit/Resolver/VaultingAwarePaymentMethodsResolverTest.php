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

namespace Tests\Sylius\PayPalPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Resolver\VaultingAwarePaymentMethodsResolver;

final class VaultingAwarePaymentMethodsResolverTest extends TestCase
{
    public function test_it_drops_the_methods_that_cannot_vault_for_an_order_that_needs_vaulting(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $offline = $this->createStub(PaymentMethodInterface::class);
        $payPalWithoutVaulting = $this->createStub(PaymentMethodInterface::class);
        $payPalWithVaulting = $this->createStub(PaymentMethodInterface::class);

        $decoratedResolver = $this->createStub(PaymentMethodsResolverInterface::class);
        $decoratedResolver->method('getSupportedMethods')->willReturn([$offline, $payPalWithoutVaulting, $payPalWithVaulting]);

        $vaultRequestProvider = $this->createStub(PayPalVaultRequestProviderInterface::class);
        $vaultRequestProvider
            ->method('isVaultingRequirementMet')
            ->willReturnCallback(static fn (OrderInterface $givenOrder, PaymentMethodInterface $paymentMethod): bool => $givenOrder !== $order || $paymentMethod !== $payPalWithoutVaulting)
        ;

        self::assertSame(
            [$offline, $payPalWithVaulting],
            (new VaultingAwarePaymentMethodsResolver($decoratedResolver, $vaultRequestProvider))->getSupportedMethods($payment),
        );
    }

    public function test_it_keeps_every_method_of_a_payment_without_an_order(): void
    {
        $paymentMethods = [$this->createStub(PaymentMethodInterface::class)];

        $decoratedResolver = $this->createStub(PaymentMethodsResolverInterface::class);
        $decoratedResolver->method('getSupportedMethods')->willReturn($paymentMethods);

        $vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $vaultRequestProvider->expects(self::never())->method('isVaultingRequirementMet');

        self::assertSame(
            $paymentMethods,
            (new VaultingAwarePaymentMethodsResolver($decoratedResolver, $vaultRequestProvider))->getSupportedMethods($this->createStub(BasePaymentInterface::class)),
        );
    }

    public function test_it_supports_what_the_decorated_resolver_supports(): void
    {
        $payment = $this->createStub(PaymentInterface::class);

        $decoratedResolver = $this->createMock(PaymentMethodsResolverInterface::class);
        $decoratedResolver->expects(self::once())->method('supports')->with($payment)->willReturn(true);

        self::assertTrue((new VaultingAwarePaymentMethodsResolver($decoratedResolver, $this->createStub(PayPalVaultRequestProviderInterface::class)))->supports($payment));
    }
}
