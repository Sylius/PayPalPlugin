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

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebSdkConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class PayPalButtonsController
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $router,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        private OrderRepositoryInterface $orderRepository,
        private AvailableCountriesProviderInterface $availableCountriesProvider,
        private LocaleProcessorInterface $localeProcessor,
        private ?PayPalWebSdkConfigurationProviderInterface $webSdkConfigurationProvider = null,
        private ?OrderTokenAssignerInterface $orderTokenAssigner = null,
        private ?ObjectManager $orderManager = null,
    ) {
        if (null === $this->webSdkConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                PayPalWebSdkConfigurationProviderInterface::class,
                self::class,
            );
        }
        if (null === $this->orderTokenAssigner || null === $this->orderManager) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of %s and an order %s to %s constructor is deprecated and will be required in 3.0.',
                OrderTokenAssignerInterface::class,
                ObjectManager::class,
                self::class,
            );
        }
    }

    public function renderProductPageButtonsAction(Request $request): Response
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/pay_from_product_page.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'createPayPalOrderFromProductUrl' => $this->router->generate('sylius_paypal_shop_add_to_cart', ['productId' => $request->attributes->getInt('productId')]),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
                'locale' => $this->localeProcessor->process($this->localeContext->getLocaleCode()),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_shop_process_paypal_order'),
                'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl(),
                'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig($channel, 'product-details'),
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
        $this->ensureOrderHasToken($order);

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/pay_from_cart_page.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'createPayPalOrderFromCartUrl' => $this->router->generate('sylius_paypal_shop_create_paypal_order_from_cart', ['tokenValue' => $order->getTokenValue()]),
                'currency' => $order->getCurrencyCode(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
                'locale' => $this->localeProcessor->process((string) $order->getLocaleCode()),
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_shop_process_paypal_order'),
                'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl(),
                'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig($channel, 'cart'),
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
        $this->ensureOrderHasToken($order);

        try {
            return new Response($this->twig->render('@SyliusPayPalPlugin/pay_from_payment_page.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_cancel_payment'),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'currency' => $order->getCurrencyCode(),
                'completePayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_shop_complete_paypal_order_from_payment_page', ['tokenValue' => $order->getTokenValue()]),
                'createPayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_shop_create_paypal_order_from_payment_page', ['tokenValue' => $order->getTokenValue()]),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
                'locale' => $this->localeProcessor->process((string) $order->getLocaleCode()),
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl(),
                'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig($channel, 'checkout'),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }

    // Both button-URL-generation paths below embed the order's tokenValue into the rendered page - but a
    // cart that reached this page without going through any checkout transition yet (the normal case for
    // the cart-page placement) never had one assigned (AssignOrderTokenListener only fires on a checkout
    // *transition*, not on order creation). Assign and persist one now, before generating any URL that
    // needs it, so the token embedded in this response actually resolves once the buyer clicks the button.
    private function ensureOrderHasToken(OrderInterface $order): void
    {
        if (null === $this->orderTokenAssigner || null === $this->orderManager) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" and an order "%s" are required to render the v6 Web SDK placements.',
                OrderTokenAssignerInterface::class,
                ObjectManager::class,
            ));
        }

        $this->orderTokenAssigner->assignTokenValueIfNotSet($order);
        $this->orderManager->flush();
    }

    private function getWebSdkConfigurationProvider(): PayPalWebSdkConfigurationProviderInterface
    {
        if (null === $this->webSdkConfigurationProvider) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to render the v6 Web SDK placements.',
                PayPalWebSdkConfigurationProviderInterface::class,
            ));
        }

        return $this->webSdkConfigurationProvider;
    }
}
