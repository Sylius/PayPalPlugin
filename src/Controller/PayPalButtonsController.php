<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Provider\CountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PayPalButtonsController
{
    /** @var Environment */
    private $twig;

    /** @var UrlGeneratorInterface */
    private $router;

    /** @var ChannelContextInterface */
    private $channelContext;

    /** @var PayPalConfigurationProviderInterface */
    private $payPalConfigurationProvider;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var CountriesProviderInterface */
    private $countriesProvider;

    public function __construct(
        Environment $twig,
        UrlGeneratorInterface $router,
        ChannelContextInterface $channelContext,
        PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        OrderRepositoryInterface $orderRepository,
        CountriesProviderInterface $countriesProvider
    ) {
        $this->twig = $twig;
        $this->router = $router;
        $this->channelContext = $channelContext;
        $this->payPalConfigurationProvider = $payPalConfigurationProvider;
        $this->orderRepository = $orderRepository;
        $this->countriesProvider = $countriesProvider;
    }

    public function renderProductPageButtonsAction(Request $request): Response
    {
        $productId = $request->attributes->getInt('productId');
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromProductPage.html.twig', [
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
                'createPayPalOrderFromProductUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_product', ['productId' => $productId]),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order'),
                'locale' => $request->getLocale(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'available_countries' => $this->countriesProvider->provide(),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }

    public function renderCartPageButtonsAction(Request $request): Response
    {
        $orderId = $request->attributes->getInt('orderId');
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromCartPage.html.twig', [
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
                'createPayPalOrderFromCartUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_cart', ['id' => $orderId]),
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order'),
                'locale' => $request->getLocale(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'available_countries' => $this->countriesProvider->provide(),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }

    public function renderPaymentPageButtonsAction(Request $request): Response
    {
        $orderId = $request->attributes->getInt('orderId');
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromPaymentPage.html.twig', [
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'completePayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_plugin_complete_paypal_order_from_payment_page', ['id' => $orderId]),
                'createPayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_payment_page', ['id' => $orderId]),
                'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_cancel_payment'),
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'locale' => $request->getLocale(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'available_countries' => $this->countriesProvider->provide(),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }
}
