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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;
use Sylius\PayPalPlugin\Provider\ActiveModeProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;

final class ActiveModeProviderTest extends TestCase
{
    private PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider;

    private ActiveModeProvider $activeModeProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);

        $this->activeModeProvider = new ActiveModeProvider($this->payPalPaymentMethodProvider);
    }

    #[Test]
    public function it_is_in_sandbox_mode_when_the_paypal_gateway_config_says_so(): void
    {
        $this->mockGatewayConfig(['sandbox' => true]);

        self::assertTrue($this->activeModeProvider->isSandbox());
    }

    #[Test]
    public function it_is_in_production_mode_when_the_paypal_gateway_config_says_so(): void
    {
        $this->mockGatewayConfig(['sandbox' => false]);

        self::assertFalse($this->activeModeProvider->isSandbox());
    }

    #[Test]
    public function it_is_in_production_mode_when_the_paypal_gateway_config_has_no_mode(): void
    {
        $this->mockGatewayConfig(['client_id' => 'CLIENT_ID']);

        self::assertFalse($this->activeModeProvider->isSandbox());
    }

    #[Test]
    public function it_is_in_production_mode_when_there_is_no_paypal_payment_method(): void
    {
        $this->payPalPaymentMethodProvider->method('provide')->willThrowException(new PayPalPaymentMethodNotFoundException());

        self::assertFalse($this->activeModeProvider->isSandbox());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mockGatewayConfig(array $config): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn($config);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->payPalPaymentMethodProvider->method('provide')->willReturn($paymentMethod);
    }
}
