<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderAction
{
    private PaymentStateManagerInterface $paymentStateManager;

    private UrlGeneratorInterface $router;

    private OrderProviderInterface $orderProvider;

    public function __construct(
        PaymentStateManagerInterface $paymentStateManager,
        UrlGeneratorInterface $router,
        OrderProviderInterface $orderProvider
    ) {
        $this->paymentStateManager = $paymentStateManager;
        $this->router = $router;
        $this->orderProvider = $orderProvider;
    }

    public function __invoke(Request $request): Response
    {
        $token = (string) $request->attributes->get('token');
        $order = $this->orderProvider->provideOrderByToken($token);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        $this->paymentStateManager->complete($payment);

        return new JsonResponse([
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
            'return_url' => $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
