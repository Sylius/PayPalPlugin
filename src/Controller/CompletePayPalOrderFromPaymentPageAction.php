<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
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
    private PaymentStateManagerInterface $paymentStateManager;

    private UrlGeneratorInterface $router;

    private OrderProviderInterface $orderProvider;

    private FactoryInterface $stateMachine;

    private ObjectManager $orderManager;

    public function __construct(
        PaymentStateManagerInterface $paymentStateManager,
        UrlGeneratorInterface $router,
        OrderProviderInterface $orderProvider,
        FactoryInterface $stateMachine,
        ObjectManager $orderManager
    ) {
        $this->paymentStateManager = $paymentStateManager;
        $this->router = $router;
        $this->orderProvider = $orderProvider;
        $this->stateMachine = $stateMachine;
        $this->orderManager = $orderManager;
    }

    public function __invoke(Request $request): Response
    {
        $orderId = $request->attributes->getInt('id');

        $order = $this->orderProvider->provideOrderById($orderId);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        $this->paymentStateManager->complete($payment);

        $orderStateMachine = $this->stateMachine->get($order, OrderCheckoutTransitions::GRAPH);
        $orderStateMachine->apply(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        $orderStateMachine->apply(OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->orderManager->flush();

        $request->getSession()->set('sylius_order_id', $order->getId());

        return new JsonResponse([
            'return_url' => $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
