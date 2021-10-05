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
    private Environment $twig;

    private PaymentRepositoryInterface $paymentRepository;

    private AvailableCountriesProviderInterface $countriesProvider;

    private CacheAuthorizeClientApiInterface $authorizeClientApi;

    private IdentityApiInterface $identityApi;

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
        $paymentId = (string) $request->attributes->get('paymentId');
        $orderToken = (string) $request->attributes->get('orderToken');

        /** @var PaymentInterface $payment */
        $payment = $this->findOneByPaymentIdOrderToken($paymentId, $orderToken);
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

    /**
     * Need to be used due to support for Sylius 1.8.
     * After dropping it, we can switch to Sylius\Component\Core\Repository\PaymentRepositoryInterface::findOneByOrderToken
     */
    private function findOneByPaymentIdOrderToken(string $paymentId, string $orderToken): ?PaymentInterface
    {
        return $this->paymentRepository
            ->createQueryBuilder('p')
            ->innerJoin('p.order', 'o')
            ->andWhere('p.id = :paymentId')
            ->andWhere('o.tokenValue = :orderToken')
            ->setParameter('paymentId', $paymentId)
            ->setParameter('orderToken', $orderToken)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
