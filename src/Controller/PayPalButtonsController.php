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

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
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
        private ?PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider = null,
    ) {
        if (null === $this->fundingSourcesConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                PayPalFundingSourcesConfigurationProviderInterface::class,
                self::class,
            );
        }
        if (null === $this->webSdkConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                PayPalWebSdkConfigurationProviderInterface::class,
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
                'paylaterEnabled' => $this->getFundingSourcesConfigurationProvider()->isPayLaterEnabled($channel),
                'venmoEnabled' => $this->getFundingSourcesConfigurationProvider()->isVenmoEnabled($channel),
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
            return new Response($this->twig->render('@SyliusPayPalPlugin/pay_from_cart_page.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'amount' => number_format($order->getTotal() / 100, 2, '.', ''),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'createPayPalOrderFromCartUrl' => $this->router->generate('sylius_paypal_shop_create_paypal_order_from_cart', ['id' => $orderId]),
                'currency' => $order->getCurrencyCode(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
                'locale' => $this->localeProcessor->process((string) $order->getLocaleCode()),
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_shop_process_paypal_order'),
                'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl(),
                'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig($channel, 'cart'),
                'paylaterEnabled' => $this->getFundingSourcesConfigurationProvider()->isPayLaterEnabled($channel),
                'venmoEnabled' => $this->getFundingSourcesConfigurationProvider()->isVenmoEnabled($channel),
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
            return new Response($this->twig->render('@SyliusPayPalPlugin/pay_from_payment_page.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'amount' => number_format($order->getTotal() / 100, 2, '.', ''),
                'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_cancel_payment'),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'currency' => $order->getCurrencyCode(),
                'completePayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_shop_complete_paypal_order_from_payment_page', ['id' => $orderId]),
                'createPayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_shop_create_paypal_order_from_payment_page', ['id' => $orderId]),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
                'locale' => $this->localeProcessor->process((string) $order->getLocaleCode()),
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl(),
                'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig($channel, 'checkout'),
                'paylaterEnabled' => $this->getFundingSourcesConfigurationProvider()->isPayLaterEnabled($channel),
                'venmoEnabled' => $this->getFundingSourcesConfigurationProvider()->isVenmoEnabled($channel),
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
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

    private function getFundingSourcesConfigurationProvider(): PayPalFundingSourcesConfigurationProviderInterface
    {
        if (null === $this->fundingSourcesConfigurationProvider) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to render the v6 Web SDK placements.',
                PayPalFundingSourcesConfigurationProviderInterface::class,
            ));
        }

        return $this->fundingSourcesConfigurationProvider;
    }
}
