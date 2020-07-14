<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Payum\Core\Payum;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderAction
{
    /** @var Payum */
    private $payum;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var PaymentStateManagerInterface */
    private $paymentStateManager;

    /** @var ObjectManager */
    private $paymentManager;

    /** @var UrlGeneratorInterface */
    private $router;

    public function __construct(
        Payum $payum,
        OrderRepositoryInterface $orderRepository,
        FactoryInterface $stateMachineFactory,
        PaymentStateManagerInterface $paymentStateManager,
        ObjectManager $paymentManager,
        UrlGeneratorInterface $router
    ) {
        $this->payum = $payum;
        $this->orderRepository = $orderRepository;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentStateManager = $paymentStateManager;
        $this->paymentManager = $paymentManager;
        $this->router = $router;
    }

    public function __invoke(Request $request): Response
    {
        $token = (string) $request->attributes->get('token');

        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneByTokenValue($token);
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
