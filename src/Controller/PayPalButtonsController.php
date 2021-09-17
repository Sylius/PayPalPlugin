<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PayPalButtonsController
{
    private Environment $twig;

    private UrlGeneratorInterface $router;

    private ChannelContextInterface $channelContext;

    private LocaleContextInterface $localeContext;

    private PayPalConfigurationProviderInterface $payPalConfigurationProvider;

    private OrderRepositoryInterface $orderRepository;

    private AvailableCountriesProviderInterface $availableCountriesProvider;

    private LocaleProcessorInterface $localeProcessor;

    public function __construct(
        Environment $twig,
        UrlGeneratorInterface $router,
        ChannelContextInterface $channelContext,
        LocaleContextInterface $localeContext,
        PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        OrderRepositoryInterface $orderRepository,
        AvailableCountriesProviderInterface $availableCountriesProvider,
        LocaleProcessorInterface $localeProcessor
    ) {
        $this->twig = $twig;
        $this->router = $router;
        $this->channelContext = $channelContext;
        $this->localeContext = $localeContext;
        $this->payPalConfigurationProvider = $payPalConfigurationProvider;
        $this->orderRepository = $orderRepository;
        $this->availableCountriesProvider = $availableCountriesProvider;
        $this->localeProcessor = $localeProcessor;
    }

    public function renderProductPageButtonsAction(Request $request): Response
    {
        $productId = $request->attributes->getInt('productId');
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromProductPage.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
                'createPayPalOrderFromProductUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_product', ['productId' => $productId]),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'locale' => $this->localeProcessor->process($this->localeContext->getLocaleCode()),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order'),
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
        /** @var OrderInterface $order */
        $order = $this->orderRepository->find($orderId);

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromCartPage.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
                'createPayPalOrderFromCartUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_cart', ['id' => $orderId]),
                'currency' => $order->getCurrencyCode(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'locale' => $this->localeProcessor->process((string) $order->getLocaleCode()),
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order'),
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
        /** @var OrderInterface $order */
        $order = $this->orderRepository->find($orderId);

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/payFromPaymentPage.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_cancel_payment'),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'currency' => $order->getCurrencyCode(),
                'completePayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_plugin_complete_paypal_order_from_payment_page', ['id' => $orderId]),
                'createPayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_payment_page', ['id' => $orderId]),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_plugin_payment_error'),
                'locale' => $this->localeProcessor->process((string) $order->getLocaleCode()),
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }
}
