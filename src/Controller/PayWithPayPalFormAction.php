<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\IdentityApiInterface;
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

    /** @var CacheAuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var IdentityApiInterface */
    private $identityApi;

    public function __construct(
        Environment $twig,
        PaymentRepositoryInterface $paymentRepository,
        AvailableCountriesProviderInterface $countriesProvider,
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        IdentityApiInterface $identityApi
    ) {
        $this->twig = $twig;
        $this->paymentRepository = $paymentRepository;
        $this->countriesProvider = $countriesProvider;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->identityApi = $identityApi;
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

        $token = $this->authorizeClientApi->authorize($paymentMethod);
        $clientToken = $this->identityApi->generateToken($token);

        return new Response($this->twig->render('@SyliusPayPalPlugin/payWithPaypal.html.twig', [
            'available_countries' => $this->countriesProvider->provide(),
            'billing_address' => $order->getBillingAddress(),
            'client_id' => $clientId,
            'client_token' => $clientToken,
            'currency' => $order->getCurrencyCode(),
            'locale' => $request->getLocale(),
            'merchant_id' => $gatewayConfig->getConfig()['merchant_id'],
            'order_token' => $order->getTokenValue(),
            'partner_attribution_id' => $partnerAttributionId,
        ]));
    }
}
