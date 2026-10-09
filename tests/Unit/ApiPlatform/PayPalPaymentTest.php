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
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class PayPalPaymentTest extends TestCase
{
    private RouterInterface&MockObject $router;

    private AvailableCountriesProviderInterface&MockObject $availableCountriesProvider;

    private PayPalPayment $payPalPayment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = $this->createMock(RouterInterface::class);
        $this->availableCountriesProvider = $this->createMock(AvailableCountriesProviderInterface::class);
        $this->payPalPayment = new PayPalPayment($this->router, $this->availableCountriesProvider);
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
    public function it_provides_proper_paypal_configuration(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $payment
            ->expects(self::once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->method('getConfig')
            ->willReturn([
                'client_id' => 'CLIENT-ID',
                'partner_attribution_id' => 'PARTNER-ATTRIBUTION-ID',
            ]);

        $payment
            ->expects(self::once())
            ->method('getOrder')
            ->willReturn($order);

        $order->method('getId')->willReturn(20);
        $order->method('getLocaleCode')->willReturn('en_US');
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTokenValue')->willReturn('TOKEN');

        $this->availableCountriesProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturn(['PL', 'US']);

        $this->router
            ->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(function ($route, $parameters = [], $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH) {
                if ($route === 'sylius_paypal_shop_cancel_payment' && $parameters === [] && $referenceType === UrlGeneratorInterface::ABSOLUTE_URL) {
                    return 'https://path-to-cancel';
                }
                if ($route === 'sylius_paypal_shop_payment_error' && $parameters === [] && $referenceType === UrlGeneratorInterface::ABSOLUTE_URL) {
                    return 'https://path-to-error';
                }

                $this->fail("Unexpected call to generate() with route: $route");
            });

        $result = $this->payPalPayment->provideConfiguration($payment);

        $expected = [
            'clientId' => 'CLIENT-ID',
            'cancelPayPalPaymentUrl' => 'https://path-to-cancel',
            'partnerAttributionId' => 'PARTNER-ATTRIBUTION-ID',
            'locale' => 'en_US',
            'orderId' => 20,
            'currency' => 'USD',
            'orderToken' => 'TOKEN',
            'errorPayPalPaymentUrl' => 'https://path-to-error',
            'available_countries' => ['PL', 'US'],
        ];

        self::assertEquals($expected, $result);
    }
}
