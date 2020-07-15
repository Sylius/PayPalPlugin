<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Payum\Core\Payum;
use Payum\Core\Request\Capture;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CreatePayPalOrderFromCartAction
{
    /** @var Payum */
    private $payum;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var ObjectManager */
    private $paymentManager;

    public function __construct(
        Payum $payum,
        OrderRepositoryInterface $orderRepository,
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager
    ) {
        $this->payum = $payum;
        $this->orderRepository = $orderRepository;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentManager = $paymentManager;
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');
        /** @var OrderInterface $order */
        $order = $this->orderRepository->find($id);

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $this
            ->payum
            ->getGateway($gatewayConfig->getGatewayName())
            ->execute(new Capture($payment))
        ;

        $this->paymentManager->flush();

        return new JsonResponse([
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
        ]);
    }
}
