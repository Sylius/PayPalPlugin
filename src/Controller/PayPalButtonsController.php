<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
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

    public function __construct(
        Environment $twig,
        UrlGeneratorInterface $router,
        ChannelContextInterface $channelContext,
        OnboardedPayPalClientIdProviderInterface $onboardedPayPalClientIdProvider
    ) {
        $this->twig = $twig;
        $this->router = $router;
        $this->channelContext = $channelContext;
        $this->onboardedPayPalClientIdProvider = $onboardedPayPalClientIdProvider;
    }

    public function renderProductPageButtonsAction(Request $request): Response
    {
        $productId = $request->attributes->get('productId');
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return new Response($this->twig->render('@SyliusPayPalPlugin/payFromProductPage.html.twig', [
            'clientId' => $this->onboardedPayPalClientIdProvider->getForChannel($channel),
            'createPayPalOrderFromProductUrl' => $this->router->generate('sylius_paypal_plugin_create_paypal_order_from_product', ['productId' => $productId]),
            'completeUrl' => $this->router->generate('sylius_shop_checkout_complete'),
            'processPayPalOrderUrl' => $this->router->generate('sylius_paypal_plugin_process_paypal_order')
        ]));
    }
}
