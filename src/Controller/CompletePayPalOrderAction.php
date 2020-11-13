<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderAction
{
    /** @var PaymentStateManagerInterface */
    private $paymentStateManager;

    /** @var UrlGeneratorInterface */
    private $router;

    /** @var PaymentProviderInterface */
    private $paymentProvider;

    /** @var OrderProviderInterface */
    private $orderProvider;

    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var ObjectManager */
    private $orderManager;

    public function __construct(
        PaymentStateManagerInterface $paymentStateManager,
        UrlGeneratorInterface $router,
        PaymentProviderInterface $paymentProvider,
        OrderProviderInterface $orderProvider,
        FactoryInterface $stateMachineFactory,
        ObjectManager $orderManager
    ) {
        $this->paymentStateManager = $paymentStateManager;
        $this->router = $router;
        $this->paymentProvider = $paymentProvider;
        $this->orderProvider = $orderProvider;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->orderManager = $orderManager;
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->query->get('id');
        $payment = $this->paymentProvider->getByPayPalOrderId($id);
        $order = $payment->getOrder();

        $this->paymentStateManager->complete($payment);

        $stateMachine = $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH);
        $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->orderManager->flush();

        $request->getSession()->set('sylius_order_id', $order->getId());

        return new JsonResponse([
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
            'return_url' => $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
