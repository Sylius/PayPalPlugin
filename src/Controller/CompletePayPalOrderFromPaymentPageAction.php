<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderFromPaymentPageAction
{
    /** @var PaymentStateManagerInterface */
    private $paymentStateManager;

    /** @var UrlGeneratorInterface */
    private $router;

    /** @var OrderProviderInterface */
    private $orderProvider;

    /** @var FactoryInterface */
    private $stateMachine;

    public function __construct(
        PaymentStateManagerInterface $paymentStateManager,
        UrlGeneratorInterface $router,
        OrderProviderInterface $orderProvider,
        FactoryInterface $stateMachine
    ) {
        $this->paymentStateManager = $paymentStateManager;
        $this->router = $router;
        $this->orderProvider = $orderProvider;
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(Request $request): Response
    {
        $orderId = $request->attributes->getInt('id');

        /** @var OrderInterface $order */
        $order = $this->orderProvider->provideOrderById($orderId);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        $this->paymentStateManager->complete($payment);

        $orderStateMachine = $this->stateMachine->get($order, OrderCheckoutTransitions::GRAPH);
        $orderStateMachine->apply(OrderCheckoutTransitions::TRANSITION_COMPLETE);

        return new JsonResponse([
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
            'return_url' => $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
