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

namespace Tests\Sylius\PayPalPlugin\Unit\Onboarding\Processor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Onboarding\Processor\BasicOnboardingProcessor;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

final class BasicOnboardingProcessorTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;

    private SellerWebhookRegistrarInterface&MockObject $sellerWebhookRegistrar;

    private RequestFactoryInterface&MockObject $requestFactory;

    private RequestInterface&MockObject $apiRequest;

    private BasicOnboardingProcessor $basicOnboardingProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->sellerWebhookRegistrar = $this->createMock(SellerWebhookRegistrarInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->apiRequest = $this->createMock(RequestInterface::class);

        $this->basicOnboardingProcessor = new BasicOnboardingProcessor(
            $this->httpClient,
            $this->sellerWebhookRegistrar,
            'https://paypal.facilitator.com',
            $this->requestFactory,
        );

        $this->apiRequest->method('withHeader')->willReturn($this->apiRequest);
    }

    #[Test]
    public function it_processes_onboarding_for_supported_payment_method_and_request(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $gatewayConfig->method('getConfig')->willReturn([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'CLIENT-SECRET',
            'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
            'merchant_id' => 'MERCHANT-ID',
        ]);

        $gatewayConfig->expects(self::once())->method('setConfig')->with([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'CLIENT-SECRET',
            'onboarding_id' => 'ONBOARDING-ID',
            'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
            'merchant_id' => 'MERCHANT-ID',
            'partner_attribution_id' => 'ATTRIBUTION-ID',
            'google_pay_enabled' => true,
            'apple_pay_enabled' => true,
        ]);

        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $request->query = new InputBag(['onboarding_id' => 'ONBOARDING-ID']);

        $this->requestFactory
            ->method('createRequest')
            ->with('GET', 'https://paypal.facilitator.com/partner-referrals/check/ONBOARDING-ID')
            ->willReturn($this->apiRequest);

        $this->httpClient->method('sendRequest')->with($this->apiRequest)->willReturn($response);

        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn(
            '{"client_id":"CLIENT-ID",
            "client_secret":"CLIENT-SECRET",
            "sylius_merchant_id":"SYLIUS-MERCHANT-ID",
            "merchant_id":"MERCHANT-ID",
            "partner_attribution_id":"ATTRIBUTION-ID"}',
        );

        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);

        $result = $this->basicOnboardingProcessor->process($paymentMethod, $request);

        self::assertSame($paymentMethod, $result);
    }

    #[Test]
    public function it_processes_onboarding_for_supported_payment_method_with_not_granted_permissions_and_request(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $gatewayConfig->method('getConfig')->willReturn([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'CLIENT-SECRET',
            'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
            'merchant_id' => 'MERCHANT-ID',
        ]);

        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $request->query = new InputBag(['onboarding_id' => 'ONBOARDING-ID', 'permissionsGranted' => false]);

        $this->requestFactory
            ->method('createRequest')
            ->with('GET', 'https://paypal.facilitator.com/partner-referrals/check/ONBOARDING-ID')
            ->willReturn($this->apiRequest);

        $this->httpClient->method('sendRequest')->with($this->apiRequest)->willReturn($response);

        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn(
            '{"client_id":"CLIENT-ID",
            "client_secret":"CLIENT-SECRET",
            "sylius_merchant_id":"SYLIUS-MERCHANT-ID",
            "merchant_id":"MERCHANT-ID",
            "partner_attribution_id":"ATTRIBUTION-ID"}',
        );

        $paymentMethod->expects(self::once())->method('setEnabled')->with(false);
        $gatewayConfig->expects(self::once())->method('setConfig')->with([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'CLIENT-SECRET',
            'onboarding_id' => 'ONBOARDING-ID',
            'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
            'merchant_id' => 'MERCHANT-ID',
            'partner_attribution_id' => 'ATTRIBUTION-ID',
            'google_pay_enabled' => true,
            'apple_pay_enabled' => true,
        ]);

        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);

        $result = $this->basicOnboardingProcessor->process($paymentMethod, $request);

        self::assertSame($paymentMethod, $result);
    }

    #[Test]
    public function it_processes_onboarding_for_supported_payment_method_with_not_granted_permissions_and_without_registered_webhook(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $gatewayConfig->method('getConfig')->willReturn([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'CLIENT-SECRET',
            'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
            'merchant_id' => 'MERCHANT-ID',
        ]);

        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $request->query = new InputBag(['onboarding_id' => 'ONBOARDING-ID', 'permissionsGranted' => false]);

        $this->requestFactory
            ->method('createRequest')
            ->with('GET', 'https://paypal.facilitator.com/partner-referrals/check/ONBOARDING-ID')
            ->willReturn($this->apiRequest);

        $this->httpClient->method('sendRequest')->with($this->apiRequest)->willReturn($response);

        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn(
            '{"client_id":"CLIENT-ID",
            "client_secret":"CLIENT-SECRET",
            "sylius_merchant_id":"SYLIUS-MERCHANT-ID",
            "merchant_id":"MERCHANT-ID",
            "partner_attribution_id":"ATTRIBUTION-ID"}',
        );

        $paymentMethod->expects($this->exactly(2))->method('setEnabled')->with(false);
        $gatewayConfig->expects(self::once())->method('setConfig')->with([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'CLIENT-SECRET',
            'onboarding_id' => 'ONBOARDING-ID',
            'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
            'merchant_id' => 'MERCHANT-ID',
            'partner_attribution_id' => 'ATTRIBUTION-ID',
            'google_pay_enabled' => true,
            'apple_pay_enabled' => true,
        ]);

        $this->sellerWebhookRegistrar
            ->method('register')
            ->with($paymentMethod)
            ->willThrowException(new PayPalWebhookUrlNotValidException());

        $result = $this->basicOnboardingProcessor->process($paymentMethod, $request);

        self::assertSame($paymentMethod, $result);
    }

    #[Test]
    public function it_throws_an_exception_when_trying_to_process_onboarding_for_unsupported_payment_method_or_request(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $request = $this->createMock(Request::class);

        $this->expectException(\DomainException::class);
        $this->basicOnboardingProcessor->process($paymentMethod, $request);
    }

    #[Test]
    public function it_supports_paypal_payment_method_with_request_containing_id(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethod::class);
        $request = $this->createMock(Request::class);

        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');

        $request->query = new InputBag(['onboarding_id' => 'FACILITATOR-ID']);

        self::assertTrue($this->basicOnboardingProcessor->supports($paymentMethod, $request));
    }

    #[Test]
    public function it_does_not_support_payment_method_that_has_no_gateway_config(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $request = $this->createMock(Request::class);

        self::assertFalse($this->basicOnboardingProcessor->supports($paymentMethod, $request));
    }

    #[Test]
    public function it_does_not_support_payment_method_that_does_not_have_paypal_as_a_gateway_factory(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $request = $this->createMock(Request::class);

        $gatewayConfig->method('getFactoryName')->willReturn('random');
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        self::assertFalse($this->basicOnboardingProcessor->supports($paymentMethod, $request));
    }

    #[Test]
    public function it_does_not_support_payment_method_that_has_client_id_is_not_set_on_request(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        self::assertFalse($this->basicOnboardingProcessor->supports($paymentMethod, new Request()));
    }

    #[Test]
    public function it_throws_error_if_facilitator_data_is_not_loaded(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(ResponseInterface::class);
        $body = $this->createMock(StreamInterface::class);

        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $request->query = new InputBag(['onboarding_id' => 'ONBOARDING-ID']);

        $this->requestFactory
            ->method('createRequest')
            ->with('GET', 'https://paypal.facilitator.com/partner-referrals/check/ONBOARDING-ID')
            ->willReturn($this->apiRequest);

        $this->httpClient->method('sendRequest')->with($this->apiRequest)->willReturn($response);

        $response->method('getBody')->willReturn($body);
        $body->method('getContents')->willReturn('{"client_id":null,"client_secret":null}');

        $this->expectException(PayPalPluginException::class);
        $this->basicOnboardingProcessor->process($paymentMethod, $request);
    }
}
