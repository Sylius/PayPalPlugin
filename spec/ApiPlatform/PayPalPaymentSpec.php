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

namespace spec\Sylius\PayPalPlugin\ApiPlatform;

use Payum\Core\Model\GatewayConfigInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class PayPalPaymentSpec extends ObjectBehavior
{
    function let(RouterInterface $router, AvailableCountriesProviderInterface $availableCountriesProvider): void
    {
        $this->beConstructedWith($router, $availableCountriesProvider);
    }

    function it_supports_paypal_payment_method(
        PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface $gatewayConfig
    ): void {
        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);

        $gatewayConfig->getFactoryName()->willReturn('sylius.pay_pal');

        $this->supports($paymentMethod)->shouldReturn(true);
    }

    function it_provides_proper_paypal_configuration(
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        OrderInterface $order,
        GatewayConfigInterface $gatewayConfig,
        AvailableCountriesProviderInterface $availableCountriesProvider,
        RouterInterface $router
    ): void {
        $payment->getMethod()->willReturn($paymentMethod);

        $paymentMethod->getGatewayConfig()->willReturn($gatewayConfig);
        $gatewayConfig->getConfig()->willReturn(
            [
                'client_id' => 'CLIENT-ID',
                'partner_attribution_id' => 'PARTNER-ATTRIBUTION-ID',
            ]
        );

        $payment->getOrder()->willReturn($order);

        $order->getId()->willReturn(20);
        $order->getLocaleCode()->willReturn('en_US');
        $order->getCurrencyCode()->willReturn('USD');
        $order->getTokenValue()->willReturn('TOKEN');

        $availableCountriesProvider->provide()->willReturn(['PL', 'US']);

        $router->generate(
            'sylius_paypal_plugin_complete_paypal_order',
            ['token' => 'TOKEN'],
            UrlGeneratorInterface::ABSOLUTE_URL
        )->willReturn('https://path-to-complete/TOKEN');

        $router->generate(
            'sylius_paypal_plugin_create_paypal_order',
            ['token' => 'TOKEN'],
            UrlGeneratorInterface::ABSOLUTE_URL
        )->willReturn('https://path-to-create/TOKEN');

        $router->generate(
            'sylius_paypal_plugin_cancel_payment',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        )->willReturn('https://path-to-cancel');

        $router->generate(
            'sylius_paypal_plugin_payment_error',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        )->willReturn('https://path-to-error');

        $this->provideConfiguration($payment)->shouldReturn(
            [
                'clientId' => 'CLIENT-ID',
                'completePayPalOrderFromPaymentPageUrl' => 'https://path-to-complete/TOKEN',
                'createPayPalOrderFromPaymentPageUrl' => 'https://path-to-create/TOKEN',
                'cancelPayPalPaymentUrl' => 'https://path-to-cancel',
                'partnerAttributionId' => 'PARTNER-ATTRIBUTION-ID',
                'locale' => 'en_US',
                'orderId' => 20,
                'currency' => 'USD',
                'orderToken' => 'TOKEN',
                'errorPayPalPaymentUrl' => 'https://path-to-error',
                'available_countries' => ['PL', 'US'],
            ]
        );
    }
}
