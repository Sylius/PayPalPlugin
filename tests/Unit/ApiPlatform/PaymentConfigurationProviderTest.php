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
use Sylius\PayPalPlugin\ApiPlatform\PaymentConfigurationProvider;
use Sylius\PayPalPlugin\Provider\PaymentSdkConfigurationProviderInterface;

final class PaymentConfigurationProviderTest extends TestCase
{
    private PaymentSdkConfigurationProviderInterface&MockObject $paymentSdkConfigurationProvider;

    private PaymentConfigurationProvider $paymentConfigurationProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentSdkConfigurationProvider = $this->createMock(PaymentSdkConfigurationProviderInterface::class);
        $this->paymentConfigurationProvider = new PaymentConfigurationProvider($this->paymentSdkConfigurationProvider);
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

        $result = $this->paymentConfigurationProvider->supports($paymentMethod);

        self::assertTrue($result);
    }

    #[Test]
    public function it_provides_the_sdk_configuration_of_the_payment_in_the_order_locale_for_a_headless_storefront(): void
    {
        $payment = $this->payment();

        $this->paymentSdkConfigurationProvider
            ->expects(self::once())
            ->method('provide')
            ->with($payment, 'en_US')
            ->willReturn($this->sdkConfiguration([
                'cardEnabled' => true,
                'googlePayEnabled' => true,
                'applePayEnabled' => true,
                'venmoEnabled' => true,
                'redirectPaymentSources' => ['trustly' => 'https://example.com/trustly.svg'],
                'messagingEnabled' => true,
                'messagingInstanceConfig' => ['clientId' => 'CLIENT_ID', 'components' => ['paypal-messages']],
            ]))
        ;

        self::assertSame([
            'webSdkScriptUrl' => 'https://www.sandbox.paypal.com/web-sdk/v6/core',
            'webSdkInstanceConfig' => ['clientId' => 'CLIENT_ID'],
            'amount' => '12.34',
            'currency' => 'USD',
            'countryCode' => 'US',
            'languageCode' => 'en',
            'sandbox' => true,
            'paymentSources' => ['paypal', 'card', 'venmo', 'google_pay', 'apple_pay', 'trustly'],
            'payLaterEnabled' => true,
            'messagingInstanceConfig' => ['clientId' => 'CLIENT_ID', 'components' => ['paypal-messages']],
        ], $this->paymentConfigurationProvider->provideConfiguration($payment));
    }

    #[Test]
    public function it_offers_only_paypal_and_no_messaging_when_nothing_else_is_enabled(): void
    {
        $payment = $this->payment();
        $this->paymentSdkConfigurationProvider->method('provide')->willReturn($this->sdkConfiguration());

        $configuration = $this->paymentConfigurationProvider->provideConfiguration($payment);

        self::assertSame(['paypal'], $configuration['paymentSources']);
        self::assertNull($configuration['messagingInstanceConfig']);
    }

    private function payment(): PaymentInterface
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getLocaleCode')->willReturn('en_US');

        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function sdkConfiguration(array $overrides = []): array
    {
        return array_merge([
            'amount' => '12.34',
            'applePayEnabled' => false,
            'countryCode' => 'US',
            'currency' => 'USD',
            'cardEnabled' => false,
            'googlePayEnabled' => false,
            'languageCode' => 'en',
            'locale' => 'en_US',
            'messagingEnabled' => false,
            'messagingInstanceConfig' => [],
            'paylaterEnabled' => true,
            'redirectPaymentSources' => [],
            'sandbox' => true,
            'venmoEnabled' => false,
            'webSdkInstanceConfig' => ['clientId' => 'CLIENT_ID'],
            'webSdkScriptUrl' => 'https://www.sandbox.paypal.com/web-sdk/v6/core',
        ], $overrides);
    }
}
