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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProvider;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\WebSdkConfigurationProviderInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class PayPalButtonsController
{
    private const VENMO_COMPONENT = 'venmo-payments';

    private CurrentPayPalLocaleProviderInterface $currentLocaleProvider;

    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $router,
        private ChannelContextInterface $channelContext,
        LocaleContextInterface $localeContext,
        private PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        private OrderRepositoryInterface $orderRepository,
        private AvailableCountriesProviderInterface $availableCountriesProvider,
        LocaleProcessorInterface $localeProcessor,
        private ?WebSdkConfigurationProviderInterface $webSdkConfigurationProvider = null,
        private ?PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider = null,
        ?CurrentPayPalLocaleProviderInterface $currentLocaleProvider = null,
        private ?PayPalPaymentMethodsResolverInterface $payPalPaymentMethodsResolver = null,
    ) {
        if (null === $this->fundingSourcesConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                PayPalFundingSourcesConfigurationProviderInterface::class,
                self::class,
            );
        }
        if (null === $this->webSdkConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                WebSdkConfigurationProviderInterface::class,
                self::class,
            );
        }
        if (null === $currentLocaleProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                CurrentPayPalLocaleProviderInterface::class,
                self::class,
            );
        }

        $this->currentLocaleProvider = $currentLocaleProvider ?? new CurrentPayPalLocaleProvider($localeContext, $localeProcessor);
    }

    public function renderProductPageButtonsAction(Request $request): Response
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        try {
            $locale = $this->currentLocaleProvider->provide();
            $venmoEnabled = $this->getFundingSourcesConfigurationProvider()->isVenmoEnabled($channel);

            return new Response($this->twig->render('@SyliusPayPalPlugin/pay_from_product_page.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'createPayPalOrderFromProductUrl' => $this->router->generate('sylius_paypal_shop_add_to_cart', ['productId' => $request->attributes->getInt('productId')]),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
                'locale' => $locale,
                'completeExpressCheckoutUrl' => $this->router->generate('sylius_paypal_shop_complete_express_checkout'),
                'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl($this->getPaymentMethod($channel)),
                'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig(
                    $this->getPaymentMethod($channel),
                    'product-details',
                    $this->getWebSdkComponents($venmoEnabled),
                    $locale,
                ),
                'paylaterEnabled' => $this->getFundingSourcesConfigurationProvider()->isPayLaterEnabled($channel),
                'venmoEnabled' => $venmoEnabled,
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
            $locale = $this->currentLocaleProvider->provide();
            $venmoEnabled = $this->getFundingSourcesConfigurationProvider()->isVenmoEnabled($channel);

            return new Response($this->twig->render('@SyliusPayPalPlugin/pay_from_cart_page.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'amount' => AmountUtils::toPayPalValue($order->getTotal(), (string) $order->getCurrencyCode()),
                'clientId' => $this->payPalConfigurationProvider->getClientId($channel),
                'createPayPalOrderFromCartUrl' => $this->router->generate('sylius_paypal_shop_create_paypal_order_from_cart', ['id' => $orderId]),
                'currency' => $order->getCurrencyCode(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
                'locale' => $locale,
                'orderId' => $orderId,
                'partnerAttributionId' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
                'completeExpressCheckoutUrl' => $this->router->generate('sylius_paypal_shop_complete_express_checkout'),
                'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl($this->getPaymentMethod($channel)),
                'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig(
                    $this->getPaymentMethod($channel),
                    'cart',
                    $this->getWebSdkComponents($venmoEnabled),
                    $locale,
                ),
                'paylaterEnabled' => $this->getFundingSourcesConfigurationProvider()->isPayLaterEnabled($channel),
                'venmoEnabled' => $venmoEnabled,
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
            $locale = $this->currentLocaleProvider->provide();
            $paymentMethod = $this->getPaymentMethod($channel, $request->attributes->getString('paymentMethodCode'));
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
            $venmoEnabled = $config->isVenmoEnabled();

            return new Response($this->twig->render('@SyliusPayPalPlugin/pay_from_payment_page.html.twig', [
                'available_countries' => $this->availableCountriesProvider->provide(),
                'amount' => AmountUtils::toPayPalValue($order->getTotal(), (string) $order->getCurrencyCode()),
                'clientId' => $config->clientId(),
                'createPayPalOrderFromCartUrl' => $this->router->generate('sylius_paypal_shop_create_paypal_order_from_cart', [
                    'id' => $orderId,
                    'paymentMethod' => $paymentMethod->getCode(),
                ]),
                'currency' => $order->getCurrencyCode(),
                'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
                'locale' => $locale,
                'orderId' => $orderId,
                'partnerAttributionId' => $config->partnerAttributionId(),
                'completeExpressCheckoutUrl' => $this->router->generate('sylius_paypal_shop_complete_express_checkout'),
                'webSdkScriptUrl' => $this->getWebSdkConfigurationProvider()->getScriptUrl($paymentMethod),
                'webSdkInstanceConfig' => $this->getWebSdkConfigurationProvider()->getInstanceConfig(
                    $paymentMethod,
                    'checkout',
                    $this->getWebSdkComponents($venmoEnabled),
                    $locale,
                ),
                'paylaterEnabled' => $config->isPayLaterEnabled(),
                'venmoEnabled' => $venmoEnabled,
            ]));
        } catch (\InvalidArgumentException $exception) {
            return new Response('');
        }
    }

    /** @return array<int, string> */
    private function getWebSdkComponents(bool $venmoEnabled): array
    {
        $components = WebSdkConfigurationProviderInterface::DEFAULT_COMPONENTS;

        if ($venmoEnabled) {
            $components[] = self::VENMO_COMPONENT;
        }

        return $components;
    }

    private function getPaymentMethod(ChannelInterface $channel, string $code = ''): PaymentMethodInterface
    {
        if (null === $this->payPalPaymentMethodsResolver) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to render the v6 Web SDK placements.',
                PayPalPaymentMethodsResolverInterface::class,
            ));
        }

        $paymentMethods = $this->payPalPaymentMethodsResolver->getInChannel($channel);
        foreach ($paymentMethods as $paymentMethod) {
            if ('' !== $code && $paymentMethod->getCode() === $code) {
                return $paymentMethod;
            }
        }

        return $paymentMethods[0] ?? throw new \InvalidArgumentException('No PayPal payment method defined');
    }

    private function getWebSdkConfigurationProvider(): WebSdkConfigurationProviderInterface
    {
        if (null === $this->webSdkConfigurationProvider) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to render the v6 Web SDK placements.',
                WebSdkConfigurationProviderInterface::class,
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
