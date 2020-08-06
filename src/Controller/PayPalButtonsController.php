<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\PayPalPlugin\Provider\OnboardedPayPalClientIdProviderInterface;
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

    /** @var OnboardedPayPalClientIdProviderInterface */
    private $onboardedPayPalClientIdProvider;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(
        Environment $twig,
        UrlGeneratorInterface $router,
        ChannelContextInterface $channelContext,
        OnboardedPayPalClientIdProviderInterface $onboardedPayPalClientIdProvider,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->twig = $twig;
        $this->router = $router;
        $this->channelContext = $channelContext;
        $this->onboardedPayPalClientIdProvider = $onboardedPayPalClientIdProvider;
        $this->orderRepository = $orderRepository;
    }

    public function renderProductPageButtonsAction(Request $request): Response
    {
        $productId = $request->attributes->get('productId');
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return new Response($this->twig->render('@SyliusPayPalPlugin/payFromProductPage.html.twig', [
            'clientId' => $this->onboardedPayPalClientIdProvider->getForChannel($channel),
            'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
            'createPayPalOrderFromProductUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_product', ['productId' => $productId]),
            'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order'),
        ]));
    }

    public function renderCartPageButtonsAction(Request $request): Response
    {
        $orderId = $request->attributes->get('orderId');
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return new Response($this->twig->render('@SyliusPayPalPlugin/payFromCartPage.html.twig', [
            'clientId' => $this->onboardedPayPalClientIdProvider->getForChannel($channel),
            'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
            'createPayPalOrderFromCartUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_cart', ['id' => $orderId]),
            'orderId' => $orderId,
            'partnerAttributionId' => $this->getPartnerAttributionId($orderId),
            'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order'),
        ]));
    }

    public function renderPaymentPageButtonsAction(Request $request): Response
    {
        $orderId = $request->attributes->get('orderId');
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return new Response($this->twig->render('@SyliusPayPalPlugin/payFromPaymentPage.html.twig', [
            'clientId' => $this->onboardedPayPalClientIdProvider->getForChannel($channel),
            'completePayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_plugin_complete_paypal_order_from_payment_page', ['id' => $orderId]),
            'createPayPalOrderFromPaymentPageUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_payment_page', ['id' => $orderId]),
            'partnerAttributionId' => $this->getPartnerAttributionId($orderId),
        ]));
    }

    /** TODO: Remove after merging https://github.com/Sylius/PayPalPlugin/pull/39 */
    private function getPartnerAttributionId(int $orderId): string
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->find($orderId);

        $config = $order->getPayments()->first()->getMethod()->getGatewayConfig()->getConfig();

        return $config['partner_attribution_id'];
    }
}
