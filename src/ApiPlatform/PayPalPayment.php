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

namespace Sylius\PayPalPlugin\ApiPlatform;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @experimental
 * This part is used to wire payment method handlers with Api Platform based on (https://github.com/Sylius/Sylius/pull/12107)
 * For now its dead code for itself, once we tag new Sylius version with new changes this code will be used.
 */
final class PayPalPayment
{
    private RouterInterface $router;

    private AvailableCountriesProviderInterface $availableCountriesProvider;

    public function __construct(RouterInterface $router, AvailableCountriesProviderInterface $availableCountriesProvider)
    {
        $this->router = $router;
        $this->availableCountriesProvider = $availableCountriesProvider;
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig->getFactoryName() === 'sylius.pay_pal';
    }

    //TODO: use provider here and in Buttons controller
    public function provideConfiguration(PaymentInterface $payment): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return [
            'clientId' => $gatewayConfig->getConfig()['client_id'],
            'completePayPalOrderFromPaymentPageUrl' => $this->router->generate(
                'sylius_paypal_plugin_complete_paypal_order',
                ['token' => $order->getTokenValue()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'createPayPalOrderFromPaymentPageUrl' => $this->router->generate(
                'sylius_paypal_plugin_create_paypal_order',
                ['token' => $order->getTokenValue()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_cancel_payment', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'partnerAttributionId' => $gatewayConfig->getConfig()['partner_attribution_id'],
            'locale' => $order->getLocaleCode(),
            'orderId' => $order->getId(),
            'currency' => $order->getCurrencyCode(),
            'orderToken' => $order->getTokenValue(),
            'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'available_countries' => $this->availableCountriesProvider->provide(),
        ];
    }
}
