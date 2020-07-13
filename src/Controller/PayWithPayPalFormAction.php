<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PayWithPayPalFormAction
{
    /** @var Environment */
    private $twig;

    /** @var PaymentRepositoryInterface */
    private $paymentRepository;

    /** @var UrlGeneratorInterface */
    private $router;

    public function __construct(
        Environment $twig,
        PaymentRepositoryInterface $paymentRepository,
        UrlGeneratorInterface $router
    ) {
        $this->twig = $twig;
        $this->paymentRepository = $paymentRepository;
        $this->router = $router;
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

        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var int $amount */
        $amount = $payment->getAmount();
        /** @var string $currencyCode */
        $currencyCode = $order->getCurrencyCode();

        return new Response($this->twig->render('@SyliusPayPalPlugin/payWithPaypal.html.twig', [
            'amount' => $amount / 100,
            'currency_code' => $currencyCode,
            'client_id' => $clientId,
            'order_token' => $order->getTokenValue(),
        ]));
    }
}
