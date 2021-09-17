<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Payum\Core\Payum;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CreatePayPalOrderAction
{
    private Payum $payum;

    private OrderRepositoryInterface $orderRepository;

    private FactoryInterface $stateMachineFactory;

    private ObjectManager $paymentManager;

    private PaymentStateManagerInterface $paymentStateManager;

    private OrderProviderInterface $orderProvider;

    private CapturePaymentResolverInterface $capturePaymentResolver;

    public function __construct(
        Payum $payum,
        OrderRepositoryInterface $orderRepository,
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        PaymentStateManagerInterface $paymentStateManager,
        OrderProviderInterface $orderProvider,
        CapturePaymentResolverInterface $capturePaymentResolver
    ) {
        $this->payum = $payum;
        $this->orderRepository = $orderRepository;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentManager = $paymentManager;
        $this->paymentStateManager = $paymentStateManager;
        $this->orderProvider = $orderProvider;
        $this->capturePaymentResolver = $capturePaymentResolver;
    }

    public function __invoke(Request $request): Response
    {
        $token = (string) $request->attributes->get('token');
        $order = $this->orderProvider->provideOrderByToken($token);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);

        $this->capturePaymentResolver->resolve($payment);

        $this->paymentStateManager->process($payment);

        return new JsonResponse([
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
        ]);
    }
}
