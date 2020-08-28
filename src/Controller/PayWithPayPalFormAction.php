<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class PayWithPayPalFormAction
{
    /** @var Environment */
    private $twig;

    /** @var PaymentRepositoryInterface */
    private $paymentRepository;

    /** @var AvailableCountriesProviderInterface */
    private $countriesProvider;

    public function __construct(
        Environment $twig,
        PaymentRepositoryInterface $paymentRepository,
        AvailableCountriesProviderInterface $countriesProvider
    ) {
        $this->twig = $twig;
        $this->paymentRepository = $paymentRepository;
        $this->countriesProvider = $countriesProvider;
    }

    public function __invoke(Request $request): Response
    {
        /** @var PaymentInterface $payment */
        $payment = $this->paymentRepository->find($request->attributes->get('id'));
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

        return new Response($this->twig->render('@SyliusPayPalPlugin/payWithPaypal.html.twig', [
            'client_id' => $clientId,
            'order_token' => $order->getTokenValue(),
            'partner_attribution_id' => $partnerAttributionId,
            'locale' => $request->getLocale(),
            'available_countries' => $this->countriesProvider->provide(),
        ]));
    }
}
