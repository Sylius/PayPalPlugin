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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Provider\PaymentSdkConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalPaymentPageContextProviderTest extends TestCase
{
    private PaymentSdkConfigurationProviderInterface&MockObject $paymentSdkConfigurationProvider;

    private PayPalPaymentPageContextProvider $provider;

    private OrderInterface&Stub $order;

    private PaymentInterface&Stub $payment;

    private PaymentMethodInterface&Stub $paymentMethod;

    private AddressInterface&Stub $billingAddress;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentSdkConfigurationProvider = $this->createMock(PaymentSdkConfigurationProviderInterface::class);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => $route);

        $this->billingAddress = $this->createStub(AddressInterface::class);

        $this->order = $this->createStub(OrderInterface::class);
        $this->order->method('getBillingAddress')->willReturn($this->billingAddress);

        $this->payment = $this->createStub(PaymentInterface::class);
        $this->payment->method('getOrder')->willReturn($this->order);

        $this->paymentMethod = $this->createStub(PaymentMethodInterface::class);

        $this->provider = new PayPalPaymentPageContextProvider($this->paymentSdkConfigurationProvider, $router);
    }

    public function test_it_implements_paypal_payment_page_context_provider_interface(): void
    {
        self::assertInstanceOf(PayPalPaymentPageContextProviderInterface::class, $this->provider);
    }

    public function test_it_provides_the_sdk_configuration_of_the_payment_request(): void
    {
        $this->paymentSdkConfigurationProvider
            ->expects(self::once())
            ->method('provide')
            ->with($this->payment, $this->paymentMethod, 'en_US')
            ->willReturn(['currency' => 'USD', 'sandbox' => true])
        ;

        $context = $this->provider->provide($this->paymentRequest(), 'en_US');

        self::assertSame('USD', $context['currency']);
        self::assertTrue($context['sandbox']);
    }

    public function test_it_provides_the_order_the_buyer_is_paying_for(): void
    {
        $this->paymentSdkConfigurationProvider->method('provide')->willReturn([]);

        $context = $this->provider->provide($this->paymentRequest(), 'en_US');

        self::assertSame($this->order, $context['order']);
        self::assertSame($this->payment, $context['payment']);
        self::assertSame($this->billingAddress, $context['billingAddress']);
    }

    public function test_it_provides_the_urls_the_page_calls(): void
    {
        $this->paymentSdkConfigurationProvider->method('provide')->willReturn([]);

        $context = $this->provider->provide($this->paymentRequest(), 'en_US');

        self::assertSame('sylius_paypal_shop_payment_error', $context['errorPayPalPaymentUrl']);
        self::assertArrayNotHasKey('createPayPalOrderUrl', $context);
    }

    private function paymentRequest(): PaymentRequestInterface
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($this->payment);
        $paymentRequest->method('getMethod')->willReturn($this->paymentMethod);

        return $paymentRequest;
    }
}
