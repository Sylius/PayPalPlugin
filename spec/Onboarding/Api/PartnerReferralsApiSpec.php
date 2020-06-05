<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Onboarding\Api;

use PhpSpec\ObjectBehavior;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Onboarding\Api\PartnerReferralsApiInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PartnerReferralsApiSpec extends ObjectBehavior
{
    function let(
        HttpClientInterface $client,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger
    ): void {
        $this->beConstructedWith($client, $urlGenerator, $logger, 'TRACKING-ID');
    }

    function it_implements_partner_referrals_api_interface(): void
    {
        $this->shouldImplement(PartnerReferralsApiInterface::class);
    }

    function it_creates_partner_referrals_and_returns_redirection_url(
        HttpClientInterface $client,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
        ResponseInterface $response
    ): void {
        $urlGenerator->generate(
            'sylius_admin_payment_method_create',
            ['factory' => 'sylius.pay_pal'],
            UrlGeneratorInterface::ABSOLUTE_URL
        )->willReturn('http://sylius.com/payment-method/create/paypal');

        $client->request('POST', 'https://api.sandbox.paypal.com/v2/customer/partner-referrals', [
            'auth_bearer' => 'ACCESS_TOKEN',
            'json' => [
                'email' => 'sb-nevei1350290@business.example.com',
                'preferred_language_code' => 'en-US',
                'tracking_id' => 'TRACKING-ID',
                'partner_config_override' => [
                    'partner_logo_url' => 'https://demo.sylius.com/assets/shop/img/logo.png', // TODO: Make sure this logo url will never change
                    'return_url' => 'http://sylius.com/payment-method/create/paypal',
                ],
                'operations' => [
                    ['operation' => 'API_INTEGRATION'],
                ],
                'products' => ['PPCP'],
            ],
        ])->willReturn($response);

        $response->getContent(false)->willReturn('{"links": [{"rel": "action_url", "href": "http://sylius.paypal/redirect-url"}]}');
        $logger->debug('{"links": [{"rel": "action_url", "href": "http://sylius.paypal/redirect-url"}]}')->shouldBeCalled();

        $response
            ->toArray()
            ->willReturn(['links' => [['rel' => 'action_url', 'href' => 'http://sylius.paypal/redirect-url']]])
        ;

        $this->create('ACCESS_TOKEN')->shouldReturn('http://sylius.paypal/redirect-url');
    }

    function it_throws_exception_if_response_has_no_action_url_defined(
        HttpClientInterface $client,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
        ResponseInterface $response
    ): void {
        $urlGenerator->generate(
            'sylius_admin_payment_method_create',
            ['factory' => 'sylius.pay_pal'],
            UrlGeneratorInterface::ABSOLUTE_URL
        )->willReturn('http://sylius.com/payment-method/create/paypal');

        $client->request('POST', 'https://api.sandbox.paypal.com/v2/customer/partner-referrals', [
            'auth_bearer' => 'ACCESS_TOKEN',
            'json' => [
                'email' => 'sb-nevei1350290@business.example.com',
                'preferred_language_code' => 'en-US',
                'tracking_id' => 'TRACKING-ID',
                'partner_config_override' => [
                    'partner_logo_url' => 'https://demo.sylius.com/assets/shop/img/logo.png', // TODO: Make sure this logo url will never change
                    'return_url' => 'http://sylius.com/payment-method/create/paypal',
                ],
                'operations' => [
                    ['operation' => 'API_INTEGRATION'],
                ],
                'products' => ['PPCP'],
            ],
        ])->willReturn($response);

        $response->getContent(false)->willReturn('{"links": [{"rel": "some_other_url", "href": "http://sylius.paypal/redirect-url"}]}');
        $logger->debug('{"links": [{"rel": "some_other_url", "href": "http://sylius.paypal/redirect-url"}]}')->shouldBeCalled();

        $response
            ->toArray()
            ->willReturn(['links' => [['rel' => 'some_other_url', 'href' => 'http://sylius.paypal/redirect-url']]])
        ;

        $this->shouldThrow(\RuntimeException::class)->during('create', ['ACCESS_TOKEN']);
    }
}
