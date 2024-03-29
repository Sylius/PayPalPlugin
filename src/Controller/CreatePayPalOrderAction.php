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
    public function __construct(
        private readonly Payum $payum,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly FactoryInterface $stateMachineFactory,
        private readonly ObjectManager $paymentManager,
        private readonly PaymentStateManagerInterface $paymentStateManager,
        private readonly OrderProviderInterface $orderProvider,
        private readonly CapturePaymentResolverInterface $capturePaymentResolver,
    ) {
        trigger_deprecation(
            'sylius/paypal-plugin',
            '1.6',
            sprintf(
                'Passing an instance of "%s" as the first argument is deprecated and will be prohibited in 2.0',
                Payum::class,
            ),
        );
        trigger_deprecation(
            'sylius/paypal-plugin',
            '1.6',
            sprintf(
                'Passing an instance of "%s" as the second argument is deprecated and will be prohibited in 2.0',
                OrderRepositoryInterface::class,
            ),
        );
        trigger_deprecation(
            'sylius/paypal-plugin',
            '1.6',
            sprintf(
                'Passing an instance of "%s" as the third argument is deprecated and will be prohibited in 2.0',
                FactoryInterface::class,
            ),
        );
        trigger_deprecation(
            'sylius/paypal-plugin',
            '1.6',
            sprintf(
                'Passing an instance of "%s" as the fourth argument is deprecated and will be prohibited in 2.0',
                ObjectManager::class,
            ),
        );
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
