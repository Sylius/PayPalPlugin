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
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventIgnoredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookSignatureNotVerifiedException;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventNotification;
use Sylius\PayPalPlugin\Provider\PayPalNotifyPaymentProvider;
use Sylius\PayPalPlugin\Resolver\WebhookEventPaymentResolverInterface;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifierInterface;
use Symfony\Component\HttpFoundation\Request;

final class PayPalNotifyPaymentProviderTest extends TestCase
{
    private WebhookRequestVerifierInterface&MockObject $webhookRequestVerifier;

    private WebhookEventPaymentResolverInterface&MockObject $webhookEventPaymentResolver;

    private PayPalNotifyPaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookRequestVerifier = $this->createMock(WebhookRequestVerifierInterface::class);
        $this->webhookEventPaymentResolver = $this->createMock(WebhookEventPaymentResolverInterface::class);

        $this->provider = new PayPalNotifyPaymentProvider($this->webhookRequestVerifier, $this->webhookEventPaymentResolver);
    }

    public function test_it_supports_only_paypal_payment_methods(): void
    {
        self::assertTrue($this->provider->supports(new Request(), $this->paymentMethod('PAYPAL', 'sylius_paypal')));
        self::assertFalse($this->provider->supports(new Request(), $this->paymentMethod('STRIPE', 'stripe')));
    }

    public function test_it_provides_the_payment_the_event_is_about(): void
    {
        $paymentMethod = $this->paymentMethod('PAYPAL');
        $payment = $this->payment($paymentMethod);
        $request = $this->webhook();

        $this->webhookRequestVerifier->expects(self::once())->method('verify')->with($request, $paymentMethod)->willReturn(true);
        $this->webhookEventPaymentResolver
            ->method('resolve')
            ->with(self::callback(static fn (PayPalWebhookEventNotification $notification): bool => 'WH-1' === $notification->id()))
            ->willReturn($payment)
        ;

        self::assertSame($payment, $this->provider->getPayment($request, $paymentMethod));
    }

    public function test_it_rejects_a_webhook_whose_signature_it_could_not_verify_before_reading_it(): void
    {
        $this->webhookRequestVerifier->method('verify')->willReturn(false);

        $this->webhookEventPaymentResolver->expects(self::never())->method('resolve');
        $this->expectException(PayPalWebhookSignatureNotVerifiedException::class);

        $this->provider->getPayment($this->webhook(), $this->paymentMethod('PAYPAL'));
    }

    public function test_it_ignores_a_webhook_that_carries_no_event(): void
    {
        $this->webhookRequestVerifier->method('verify')->willReturn(true);

        $this->webhookEventPaymentResolver->expects(self::never())->method('resolve');
        $this->expectException(PayPalWebhookEventIgnoredException::class);

        $this->provider->getPayment(new Request(content: '{"foo": "bar"}'), $this->paymentMethod('PAYPAL'));
    }

    public function test_it_ignores_an_event_that_matches_no_payment(): void
    {
        $this->webhookRequestVerifier->method('verify')->willReturn(true);
        $this->webhookEventPaymentResolver->method('resolve')->willReturn(null);

        $this->expectException(PayPalWebhookEventIgnoredException::class);

        $this->provider->getPayment($this->webhook(), $this->paymentMethod('PAYPAL'));
    }

    public function test_it_ignores_an_event_about_a_payment_of_another_payment_method(): void
    {
        $this->webhookRequestVerifier->method('verify')->willReturn(true);
        $this->webhookEventPaymentResolver->method('resolve')->willReturn($this->payment($this->paymentMethod('PAYPAL_OTHER')));

        $this->expectException(PayPalWebhookEventIgnoredException::class);

        $this->provider->getPayment($this->webhook(), $this->paymentMethod('PAYPAL'));
    }

    private function webhook(): Request
    {
        return new Request(content: (string) json_encode([
            'id' => 'WH-1',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource_type' => 'capture',
            'resource' => ['id' => 'CAPTURE_ID'],
        ]));
    }

    private function paymentMethod(string $code, string $factoryName = 'sylius_paypal'): PaymentMethodInterface
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getCode')->willReturn($code);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }

    private function payment(PaymentMethodInterface $paymentMethod): PaymentInterface
    {
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        return $payment;
    }
}
