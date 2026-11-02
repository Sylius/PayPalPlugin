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

namespace Sylius\PayPalPlugin\ApiPlatform;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class PayPalPayment
{
    private RouterInterface $router;

    private AvailableCountriesProviderInterface $availableCountriesProvider;

    public function __construct(
        RouterInterface $router,
        AvailableCountriesProviderInterface $availableCountriesProvider,
    ) {
        $this->router = $router;
        $this->availableCountriesProvider = $availableCountriesProvider;
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME;
    }

    public function provideConfiguration(PaymentInterface $payment): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        return [
            'clientId' => $config->clientId(),
            'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_cancel_payment', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'partnerAttributionId' => $config->partnerAttributionId(),
            'locale' => $order->getLocaleCode(),
            'orderId' => $order->getId(),
            'currency' => $order->getCurrencyCode(),
            'orderToken' => $order->getTokenValue(),
            'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'available_countries' => $this->availableCountriesProvider->provide(),
        ];
    }
}
