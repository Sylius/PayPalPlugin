<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\IdentityApiInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
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

    /** @var ChannelContextInterface */
    private $channelContext;

    /** @var LocaleContextInterface */
    private $localeContext;

    /** @var PayPalConfigurationProviderInterface */
    private $payPalConfigurationProvider;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var AvailableCountriesProviderInterface */
    private $availableCountriesProvider;

    /** @var LocaleProcessorInterface */
    private $localeProcessor;

    /** @var CacheAuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var IdentityApiInterface */
    private $identityApi;

    public function __construct(
        Environment $twig,
        UrlGeneratorInterface $router,
        ChannelContextInterface $channelContext,
        LocaleContextInterface $localeContext,
        PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        OrderRepositoryInterface $orderRepository,
        AvailableCountriesProviderInterface $availableCountriesProvider,
        LocaleProcessorInterface $localeProcessor,
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        IdentityApiInterface $identityApi
    ) {
        $this->twig = $twig;
        $this->router = $router;
        $this->channelContext = $channelContext;
        $this->localeContext = $localeContext;
        $this->payPalConfigurationProvider = $payPalConfigurationProvider;
        $this->orderRepository = $orderRepository;
        $this->availableCountriesProvider = $availableCountriesProvider;
        $this->localeProcessor = $localeProcessor;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->identityApi = $identityApi;
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

    public function renderPayPalPaymentAction(Request $request): Response
    {
        $orderId = $request->attributes->getInt('orderId');
        /** @var OrderInterface $order */
        $order = $this->orderRepository->find($orderId);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        /** @var string $clientId */
        $clientId = $gatewayConfig->getConfig()['client_id'];
        /** @var string $partnerAttributionId */
        $partnerAttributionId = $gatewayConfig->getConfig()['partner_attribution_id'];

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $token = $this->authorizeClientApi->authorize($paymentMethod);
        $clientToken = $this->identityApi->generateToken($token);

        return new Response($this->twig->render('@SyliusPayPalPlugin/payWithPaypal.html.twig', [
            'available_countries' => $this->availableCountriesProvider->provide(),
            'billing_address' => $order->getBillingAddress(),
            'client_id' => $clientId,
            'client_token' => $clientToken,
            'currency' => $order->getCurrencyCode(),
            'locale' => $this->localeProcessor->process((string) $order->getLocaleCode()),
            'merchant_id' => $gatewayConfig->getConfig()['merchant_id'],
            'order_token' => 'test',
            'partner_attribution_id' => $partnerAttributionId,
        ]));
    }
}
