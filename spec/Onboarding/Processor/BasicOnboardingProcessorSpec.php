<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Onboarding\Processor;

use GuzzleHttp\ClientInterface;
use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

final class BasicOnboardingProcessorSpec extends ObjectBehavior
{
    function let(
        ClientInterface $httpClient,
        SellerWebhookRegistrarInterface $sellerWebhookRegistrar
    ): void {
        $this->beConstructedWith($httpClient, $sellerWebhookRegistrar, 'https://paypal.facilitator.com');
    }

    function it_processes_onboarding_for_supported_payment_method_and_request(
        ClientInterface $httpClient,
        SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        ResponseInterface $response,
        StreamInterface $body,
        GatewayConfigInterface $gatewayConfig,
        PaymentMethodInterface $paymentMethod,
        Request $request
    ): void {
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(
            [
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
                'merchant_id' => 'MERCHANT-ID',
            ]
        );

        $gatewayConfig->setConfig(
            [
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'onboarding_id' => 'ONBOARDING-ID',
                'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
                'merchant_id' => 'MERCHANT-ID',
                'partner_attribution_id' => 'ATTRIBUTION-ID',
            ]
        )->shouldBeCalled();

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $request->query = new ParameterBag(['onboarding_id' => 'ONBOARDING-ID']);

        $httpClient
            ->request(
                'GET',
                'https://paypal.facilitator.com/partner-referrals/check/ONBOARDING-ID',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ]
            )
            ->willReturn($response)
        ;

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn(
            '{"client_id":"CLIENT-ID",
            "client_secret":"CLIENT-SECRET",
            "sylius_merchant_id":"SYLIUS-MERCHANT-ID",
            "merchant_id":"MERCHANT-ID",
            "partner_attribution_id":"ATTRIBUTION-ID"}'
        );

        $sellerWebhookRegistrar->register($paymentMethod)->shouldBeCalled();

        $this->process($paymentMethod, $request)->shouldReturn($paymentMethod);
    }

    function it_processes_onboarding_for_supported_payment_method_with_not_granted_permissions_and_request(
        ClientInterface $httpClient,
        SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        ResponseInterface $response,
        StreamInterface $body,
        GatewayConfigInterface $gatewayConfig,
        PaymentMethodInterface $paymentMethod,
        Request $request
    ): void {
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(
            [
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
                'merchant_id' => 'MERCHANT-ID',
            ]
        );

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $request->query = new ParameterBag(['onboarding_id' => 'ONBOARDING-ID', 'permissionsGranted' => false]);

        $httpClient
            ->request(
                'GET',
                'https://paypal.facilitator.com/partner-referrals/check/ONBOARDING-ID',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ]
            )
            ->willReturn($response)
        ;

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn(
            '{"client_id":"CLIENT-ID",
            "client_secret":"CLIENT-SECRET",
            "sylius_merchant_id":"SYLIUS-MERCHANT-ID",
            "merchant_id":"MERCHANT-ID",
            "partner_attribution_id":"ATTRIBUTION-ID"}'
        );

        $paymentMethod->setEnabled(false)->shouldBeCalled();
        $gatewayConfig->setConfig(
            [
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'onboarding_id' => 'ONBOARDING-ID',
                'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
                'merchant_id' => 'MERCHANT-ID',
                'partner_attribution_id' => 'ATTRIBUTION-ID',
            ]
        )->shouldBeCalled();

        $sellerWebhookRegistrar->register($paymentMethod)->shouldBeCalled();

        $this->process($paymentMethod, $request)->shouldReturn($paymentMethod);
    }

    function it_processes_onboarding_for_supported_payment_method_with_not_granted_permissions_and_without_registered_webhook(
        ClientInterface $httpClient,
        SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        ResponseInterface $response,
        StreamInterface $body,
        GatewayConfigInterface $gatewayConfig,
        PaymentMethodInterface $paymentMethod,
        Request $request
    ): void {
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');
        $gatewayConfig->getConfig()->willReturn(
            [
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
                'merchant_id' => 'MERCHANT-ID',
            ]
        );

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $request->query = new ParameterBag(['onboarding_id' => 'ONBOARDING-ID', 'permissionsGranted' => false]);

        $httpClient
            ->request(
                'GET',
                'https://paypal.facilitator.com/partner-referrals/check/ONBOARDING-ID',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ]
            )
            ->willReturn($response)
        ;

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn(
            '{"client_id":"CLIENT-ID",
            "client_secret":"CLIENT-SECRET",
            "sylius_merchant_id":"SYLIUS-MERCHANT-ID",
            "merchant_id":"MERCHANT-ID",
            "partner_attribution_id":"ATTRIBUTION-ID"}'
        );

        $paymentMethod->setEnabled(false)->shouldBeCalled();
        $gatewayConfig->setConfig(
            [
                'client_id' => 'CLIENT-ID',
                'client_secret' => 'CLIENT-SECRET',
                'onboarding_id' => 'ONBOARDING-ID',
                'sylius_merchant_id' => 'SYLIUS-MERCHANT-ID',
                'merchant_id' => 'MERCHANT-ID',
                'partner_attribution_id' => 'ATTRIBUTION-ID',
            ]
        )->shouldBeCalled();

        $sellerWebhookRegistrar->register($paymentMethod)->willThrow(PayPalWebhookUrlNotValidException::class);
        $paymentMethod->setEnabled(false)->shouldBeCalled();

        $this->process($paymentMethod, $request)->shouldReturn($paymentMethod);
    }

    function it_throws_an_exception_when_trying_to_process_onboarding_for_unsupported_payment_method_or_request(
        PaymentMethodInterface $paymentMethod,
        Request $request
    ): void {
        $this
            ->shouldThrow(\DomainException::class)
            ->during('process', [$paymentMethod, $request])
        ;
    }

    function it_supports_paypal_payment_method_with_request_containing_id(
        GatewayConfigInterface $gatewayConfig,
        PaymentMethod $paymentMethod,
        Request $request
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $request->query = new ParameterBag(['onboarding_id' => 'FACILITATOR-ID']);

        $this->supports($paymentMethod, $request)->shouldReturn(true);
    }

    function it_does_not_support_payment_method_that_has_no_gateway_config(
        PaymentMethodInterface $paymentMethod,
        Request $request
    ): void {
        $this->supports($paymentMethod, $request)->shouldReturn(false);
    }

    function it_does_not_support_payment_method_that_does_not_have_paypal_as_a_gateway_factory(
        GatewayConfigInterface $gatewayConfig,
        PaymentMethodInterface $paymentMethod,
        Request $request
    ): void {
        $gatewayConfig->getFactoryName()->willReturn('random');

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $this->supports($paymentMethod, $request)->shouldReturn(false);
    }

    function it_does_not_support_payment_method_that_has_client_id_is_not_set_on_request(
        GatewayConfigInterface $gatewayConfig,
        PaymentMethodInterface $paymentMethod
    ): void {
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $this->supports($paymentMethod, new Request())->shouldReturn(false);
    }

    function it_throws_error_if_facilitator_data_is_not_loaded(
        ClientInterface $httpClient,
        ResponseInterface $response,
        StreamInterface $body,
        GatewayConfigInterface $gatewayConfig,
        PaymentMethodInterface $paymentMethod,
        Request $request
    ): void {
        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $request->query = new ParameterBag(['onboarding_id' => 'ONBOARDING-ID']);

        $httpClient
            ->request(
                'GET',
                'https://paypal.facilitator.com/partner-referrals/check/ONBOARDING-ID',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ]
            )
            ->willReturn($response)
        ;

        $response->getBody()->willReturn($body);
        $body->getContents()->willReturn('{"client_id":null,"client_secret":null}');

        $this
            ->shouldThrow(PayPalPluginException::class)
            ->during('process', [$paymentMethod, $request])
        ;
    }
}
