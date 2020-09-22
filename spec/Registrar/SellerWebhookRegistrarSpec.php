<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Registrar;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SellerWebhookRegistrarSpec extends ObjectBehavior
{
    function let(
        AuthorizeClientApiInterface $authorizeClientApi,
        UrlGeneratorInterface $urlGenerator,
        WebhookApiInterface $webhookApi
    ): void {
        $this->beConstructedWith($authorizeClientApi, $urlGenerator, $webhookApi);
    }

    function it_implements_seller_webhook_registrar_interface(): void
    {
        $this->shouldImplement(SellerWebhookRegistrarInterface::class);
    }

    function it_registers_sellers_webhook(
        AuthorizeClientApiInterface $authorizeClientApi,
        UrlGeneratorInterface $urlGenerator,
        WebhookApiInterface $webhookApi,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);

        $authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET')->willReturn('TOKEN');
        $urlGenerator
            ->generate('sylius_paypal_plugin_webhook_refund_order', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://webhook-url.com')
        ;

        $webhookApi->register('TOKEN', 'https://webhook-url.com')->willReturn(['name' => 'WEBHOOK_REGISTERED']);

        $this->register($paymentMethod);
    }

    function it_throws_exception_if_webhook_could_not_be_registered(
        AuthorizeClientApiInterface $authorizeClientApi,
        UrlGeneratorInterface $urlGenerator,
        WebhookApiInterface $webhookApi,
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET']);

        $authorizeClientApi->authorize('CLIENT_ID', 'CLIENT_SECRET')->willReturn('TOKEN');
        $urlGenerator
            ->generate('sylius_paypal_plugin_webhook_refund_order', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://webhook-url.com')
        ;

        $webhookApi->register('TOKEN', 'https://webhook-url.com')->willReturn(['name' => 'VALIDATION_ERROR']);

        $this
            ->shouldThrow(PayPalWebhookUrlNotValidException::class)
            ->during('register', [$paymentMethod])
        ;
    }
}
