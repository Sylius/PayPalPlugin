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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProvider;
use Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface;

final class PayPalVaultRequestProviderTest extends TestCase
{
    public function test_it_provides_the_vault_request_the_resolver_gives_for_a_merchant_that_can_vault(): void
    {
        $payment = $this->paymentWithGateway('sylius_paypal', ['vaulting_enabled' => true]);
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);

        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with($payment)->willReturn($vaultRequest);

        self::assertSame($vaultRequest, (new PayPalVaultRequestProvider($resolver))->provide($payment));
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

        self::assertNull((new PayPalVaultRequestProvider($resolver))->provide($this->paymentWithGateway($factoryName, $config)));
    }

    public function test_it_provides_no_vault_request_for_a_payment_without_a_method(): void
    {
        $resolver = $this->createMock(VaultRequestResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getMethod')->willReturn(null);

        self::assertNull((new PayPalVaultRequestProvider($resolver))->provide($payment));
    }

    /** @param array<string, mixed> $config */
    private function paymentWithGateway(string $factoryName, array $config): PaymentInterface
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);
        $gatewayConfig->method('getConfig')->willReturn($config);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        return $payment;
    }
}
