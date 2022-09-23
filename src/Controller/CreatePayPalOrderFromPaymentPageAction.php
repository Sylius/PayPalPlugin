<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\GuzzleException;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class CreatePayPalOrderFromPaymentPageAction
{
    private FactoryInterface $stateMachineFactory;

    private ObjectManager $paymentManager;

    private PaymentStateManagerInterface $paymentStateManager;

    private OrderProviderInterface $orderProvider;

    private CapturePaymentResolverInterface $capturePaymentResolver;

    public function __construct(
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentStateManagerInterface $paymentStateManager,
        OrderProviderInterface $orderProvider,
        CapturePaymentResolverInterface $capturePaymentResolver
    ) {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentManager = $paymentManager;
        $this->paymentStateManager = $paymentStateManager;
        $this->orderProvider = $orderProvider;
        $this->capturePaymentResolver = $capturePaymentResolver;
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');

        $order = $this->orderProvider->provideOrderById($id);

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        $orderCheckoutStateMachine = $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH);
        $orderCheckoutStateMachine->apply(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        try {
            $this->capturePaymentResolver->resolve($payment);
        } catch (GuzzleException $exception) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius.pay_pal.something_went_wrong');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->paymentStateManager->create($payment);
        $this->paymentStateManager->process($payment);

        return new JsonResponse([
            'order_id' => $payment->getDetails()['paypal_order_id'],
        ]);
    }
}
