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
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolver;

final class PayPalPaymentMethodsResolverTest extends TestCase
{
    public function test_returns_sorted_paypal_methods_from_channel(): void
    {
        $repository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $paypalGatewayLow = $this->createMock(GatewayConfigInterface::class);
        $paypalGatewayLow->method('getFactoryName')->willReturn(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);

        $paypalMethodLow = $this->createMock(PaymentMethodInterface::class);
        $paypalMethodLow->method('getGatewayConfig')->willReturn($paypalGatewayLow);
        $paypalMethodLow->method('getPosition')->willReturn(10);

        $paypalGatewayHigh = $this->createMock(GatewayConfigInterface::class);
        $paypalGatewayHigh->method('getFactoryName')->willReturn(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);

        $paypalMethodHigh = $this->createMock(PaymentMethodInterface::class);
        $paypalMethodHigh->method('getGatewayConfig')->willReturn($paypalGatewayHigh);
        $paypalMethodHigh->method('getPosition')->willReturn(5);

        $otherGateway = $this->createMock(GatewayConfigInterface::class);
        $otherGateway->method('getFactoryName')->willReturn('other_factory');

        $otherMethod = $this->createMock(PaymentMethodInterface::class);
        $otherMethod->method('getGatewayConfig')->willReturn($otherGateway);

        $repository
            ->expects($this->once())
            ->method('findEnabledForChannel')
            ->with($channel)
            ->willReturn([$paypalMethodLow, $otherMethod, $paypalMethodHigh]);

        $resolver = new PayPalPaymentMethodsResolver($repository);

        $result = $resolver->getInChannel($channel);

        $this->assertSame([$paypalMethodHigh, $paypalMethodLow], $result);
    }

    public function test_returns_empty_array_when_channel_has_no_paypal_methods(): void
    {
        $repository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $nonPaypalGateway = $this->createMock(GatewayConfigInterface::class);
        $nonPaypalGateway->method('getFactoryName')->willReturn('non_paypal');

        $nonPaypalMethod = $this->createMock(PaymentMethodInterface::class);
        $nonPaypalMethod->method('getGatewayConfig')->willReturn($nonPaypalGateway);

        $repository
            ->expects($this->once())
            ->method('findEnabledForChannel')
            ->with($channel)
            ->willReturn([$nonPaypalMethod]);

        $resolver = new PayPalPaymentMethodsResolver($repository);

        $this->assertSame([], $resolver->getInChannel($channel));
    }

    public function test_returns_empty_array_when_channel_has_no_payment_methods(): void
    {
        $repository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $repository
            ->expects($this->once())
            ->method('findEnabledForChannel')
            ->with($channel)
            ->willReturn([]);

        $resolver = new PayPalPaymentMethodsResolver($repository);

        $this->assertSame([], $resolver->getInChannel($channel));
    }

    public function test_skips_methods_without_gateway_configuration(): void
    {
        $repository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $channel = $this->createMock(ChannelInterface::class);

        $paypalGateway = $this->createMock(GatewayConfigInterface::class);
        $paypalGateway->method('getFactoryName')->willReturn(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);

        $paypalMethod = $this->createMock(PaymentMethodInterface::class);
        $paypalMethod->method('getGatewayConfig')->willReturn($paypalGateway);
        $paypalMethod->method('getPosition')->willReturn(1);

        $methodWithoutGateway = $this->createMock(PaymentMethodInterface::class);
        $methodWithoutGateway->method('getGatewayConfig')->willReturn(null);

        $repository
            ->expects($this->once())
            ->method('findEnabledForChannel')
            ->with($channel)
            ->willReturn([$methodWithoutGateway, $paypalMethod]);

        $resolver = new PayPalPaymentMethodsResolver($repository);

        $this->assertSame([$paypalMethod], $resolver->getInChannel($channel));
    }
}
