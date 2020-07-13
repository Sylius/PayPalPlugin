<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CompleteOrderPaymentResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderByIdAction
{
    /** @var PaymentStateManagerInterface */
    private $paymentStateManager;

    /** @var ObjectManager */
    private $paymentManager;

    /** @var UrlGeneratorInterface */
    private $router;

    /** @var OrderProviderInterface */
    private $orderProvider;

    /** @var CompleteOrderPaymentResolver */
    private $completeOrderPaymentResolver;

    public function __construct(
        PaymentStateManagerInterface $paymentStateManager,
        ObjectManager $paymentManager,
        UrlGeneratorInterface $router,
        OrderProviderInterface $orderProvider,
        CompleteOrderPaymentResolver $completeOrderPaymentResolver
    ) {
        $this->paymentStateManager = $paymentStateManager;
        $this->paymentManager = $paymentManager;
        $this->router = $router;
        $this->orderProvider = $orderProvider;
        $this->completeOrderPaymentResolver = $completeOrderPaymentResolver;
    }

    public function __invoke(Request $request): Response
    {
        $paypalOrderId = (string) $request->attributes->get('orderId');
        $orderId = $request->attributes->getInt('syliusOrderId');

        /** @var OrderInterface $order */
        $order = $this->orderProvider->provideOrderById($orderId);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        $this->completeOrderPaymentResolver->resolve($payment, $paypalOrderId);

        $this->paymentStateManager->complete($payment);

        return new JsonResponse([
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
            'return_url' => $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
