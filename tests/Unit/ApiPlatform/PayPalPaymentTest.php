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

namespace Tests\Sylius\PayPalPlugin\Unit\ApiPlatform;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\ApiPlatform\PayPalPayment;
use Sylius\PayPalPlugin\Provider\PaymentSdkConfigurationProviderInterface;

final class PayPalPaymentTest extends TestCase
{
    private PaymentSdkConfigurationProviderInterface&MockObject $paymentSdkConfigurationProvider;

    private PayPalPayment $payPalPayment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentSdkConfigurationProvider = $this->createMock(PaymentSdkConfigurationProviderInterface::class);
        $this->payPalPayment = new PayPalPayment($this->paymentSdkConfigurationProvider);
    }

    #[Test]
    public function it_supports_paypal_payment_method(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getFactoryName')
            ->willReturn('sylius_paypal');

        $result = $this->payPalPayment->supports($paymentMethod);

        self::assertTrue($result);
    }

    #[Test]
    public function it_provides_the_sdk_configuration_of_the_payment_in_the_order_locale(): void
    {
        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $order = $this->createStub(OrderInterface::class);
        $order->method('getLocaleCode')->willReturn('en_US');

        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getOrder')->willReturn($order);

        $this->paymentSdkConfigurationProvider
            ->expects(self::once())
            ->method('provide')
            ->with($payment, $paymentMethod, 'en_US')
            ->willReturn(['webSdkScriptUrl' => 'https://www.sandbox.paypal.com/web-sdk/v6/core'])
        ;

        self::assertSame(
            ['webSdkScriptUrl' => 'https://www.sandbox.paypal.com/web-sdk/v6/core'],
            $this->payPalPayment->provideConfiguration($payment),
        );
    }
}
