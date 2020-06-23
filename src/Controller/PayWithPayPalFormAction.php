<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

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
        $clientId = $paymentMethod->getGatewayConfig()->getConfig()['client_id'];

        return new Response($this->twig->render('@SyliusPayPalPlugin/payWithPaypal.html.twig', [
            'amount' => $payment->getAmount()/100,
            'currency_code' => $payment->getOrder()->getCurrencyCode(),
            'client_id' => $clientId,
            'complete_url' => $this->router->generate(
                'sylius_paypal_plugin_complete_pay_pal_payment', ['id' => $payment->getId()]
            )
        ]));
    }
}
