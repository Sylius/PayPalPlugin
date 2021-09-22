<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\GuzzleException;
use Payum\Core\Payum;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class CreatePayPalOrderFromCartAction
{
    private Payum $payum;

    private OrderRepositoryInterface $orderRepository;

    private FactoryInterface $stateMachineFactory;

    private ObjectManager $paymentManager;

    private OrderProviderInterface $orderProvider;

    private CapturePaymentResolverInterface $capturePaymentResolver;

    public function __construct(
        Payum $payum,
        OrderRepositoryInterface $orderRepository,
        FactoryInterface $stateMachineFactory,
        ObjectManager $paymentManager,
        OrderProviderInterface $orderProvider,
        CapturePaymentResolverInterface $capturePaymentResolver
    ) {
        $this->payum = $payum;
        $this->orderRepository = $orderRepository;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentManager = $paymentManager;
        $this->orderProvider = $orderProvider;
        $this->capturePaymentResolver = $capturePaymentResolver;
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');
        $order = $this->orderProvider->provideOrderById($id);

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        try {
            $this->capturePaymentResolver->resolve($payment);
        } catch (GuzzleException $exception) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius.pay_pal.something_went_wrong');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->paymentManager->flush();

        return new JsonResponse([
            'id' => $order->getId(),
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
        ]);
    }
}
