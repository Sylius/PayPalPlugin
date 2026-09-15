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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebSdkConfigurationProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalPaymentPageContextProviderTest extends TestCase
{
    private const SCRIPT_URL = 'https://www.sandbox.paypal.com/web-sdk/v6/core';

    private PayPalWebSdkConfigurationProviderInterface&MockObject $webSdkConfigurationProvider;

    private PayPalPaymentPageContextProvider $provider;

    private PaymentInterface&Stub $payment;

    private AddressInterface&Stub $billingAddress;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webSdkConfigurationProvider = $this->createMock(PayPalWebSdkConfigurationProviderInterface::class);
        $this->webSdkConfigurationProvider->method('getScriptUrl')->willReturn(self::SCRIPT_URL);

        $localeProcessor = $this->createStub(LocaleProcessorInterface::class);
        $localeProcessor->method('process')->willReturnArgument(0);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => $route);

        $this->billingAddress = $this->createStub(AddressInterface::class);

        $order = $this->createStub(OrderInterface::class);
        $order->method('getChannel')->willReturn($this->createStub(ChannelInterface::class));
        $order->method('getTokenValue')->willReturn('ORDER_TOKEN');
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getBillingAddress')->willReturn($this->billingAddress);

        $this->payment = $this->createStub(PaymentInterface::class);
        $this->payment->method('getOrder')->willReturn($order);

        $this->provider = new PayPalPaymentPageContextProvider(
            $this->webSdkConfigurationProvider,
            $router,
            $localeProcessor,
        );
    }

    public function test_it_implements_paypal_payment_page_context_provider_interface(): void
    {
        self::assertInstanceOf(PayPalPaymentPageContextProviderInterface::class, $this->provider);
    }

    public function test_it_provides_the_urls_the_page_calls(): void
    {
        $context = $this->provider->provide($this->payment, 'en_US');

        self::assertSame('sylius_paypal_shop_create_paypal_order', $context['createPayPalOrderUrl']);
        self::assertSame('sylius_paypal_shop_complete_paypal_order', $context['completePayPalOrderUrl']);
        self::assertSame('sylius_paypal_shop_cancel_checkout_payment', $context['cancelPayPalPaymentUrl']);
        self::assertSame('sylius_paypal_shop_payment_error', $context['errorPayPalPaymentUrl']);
    }

    public function test_it_provides_the_order_the_buyer_is_paying_for(): void
    {
        $context = $this->provider->provide($this->payment, 'en_US');

        self::assertSame($this->payment, $context['payment']);
        self::assertSame($this->billingAddress, $context['billingAddress']);
        self::assertSame('USD', $context['currency']);
    }

    public function test_it_asks_the_sdk_instance_for_the_card_fields_component(): void
    {
        $this->webSdkConfigurationProvider
            ->expects(self::once())
            ->method('getInstanceConfig')
            ->with(self::anything(), 'checkout', ['paypal-payments', 'card-fields'], 'en_US')
            ->willReturn(['clientId' => 'CLIENT_ID'])
        ;

        $context = $this->provider->provide($this->payment, 'en_US');

        self::assertSame(['clientId' => 'CLIENT_ID'], $context['webSdkInstanceConfig']);
        self::assertSame(self::SCRIPT_URL, $context['webSdkScriptUrl']);
    }
}
