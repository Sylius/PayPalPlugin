<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
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

    /** @var PayPalConfigurationProviderInterface */
    private $payPalConfigurationProvider;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var AvailableCountriesProviderInterface */
    private $availableCountriesProvider;

    public function __construct(
        Environment $twig,
        UrlGeneratorInterface $router,
        PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        OrderRepositoryInterface $orderRepository,
        AvailableCountriesProviderInterface $availableCountriesProvider
    ) {
        $this->twig = $twig;
        $this->router = $router;
        $this->payPalConfigurationProvider = $payPalConfigurationProvider;
        $this->orderRepository = $orderRepository;
        $this->availableCountriesProvider = $availableCountriesProvider;
    }

    public function renderProductPageButtonsAction(Request $request): Response
    {
        $productId = $request->attributes->getInt('productId');

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromProductPage.html.twig', [
                'clientId' => $this->payPalConfigurationProvider->getClientId(),
                'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
                'createPayPalOrderFromProductUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_product', ['productId' => $productId]),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order'),
                'locale' => $request->getLocale(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'available_countries' => $this->availableCountriesProvider->provide(),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }

    public function renderCartPageButtonsAction(Request $request): Response
    {
        $orderId = $request->attributes->getInt('orderId');

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromCartPage.html.twig', [
                'clientId' => $this->payPalConfigurationProvider->getClientId(),
                'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
                'createPayPalOrderFromCartUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_cart', ['id' => $orderId]),
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId(),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order'),
                'locale' => $request->getLocale(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'available_countries' => $this->availableCountriesProvider->provide(),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }

    public function renderPaymentPageButtonsAction(Request $request): Response
    {
        $orderId = $request->attributes->getInt('orderId');

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromPaymentPage.html.twig', [
                'clientId' => $this->payPalConfigurationProvider->getClientId(),
                'completePayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_plugin_complete_paypal_order_from_payment_page', ['id' => $orderId]),
                'createPayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_payment_page', ['id' => $orderId]),
                'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_cancel_payment'),
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId(),
                'locale' => $request->getLocale(),
                'orderId' => $orderId,
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'available_countries' => $this->availableCountriesProvider->provide(),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }
}
