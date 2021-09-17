<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

final class CancelPayPalOrderAction
{
    private PaymentProviderInterface $paymentProvider;

    private OrderRepositoryInterface $orderRepository;

    private FlashBag $flashBag;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        OrderRepositoryInterface $orderRepository,
        FlashBag $flashBag
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->orderRepository = $orderRepository;
        $this->flashBag = $flashBag;
    }

    public function __invoke(Request $request): Response
    {
        $content = (array) json_decode((string) $request->getContent(false), true);

        $payment = $this->paymentProvider->getByPayPalOrderId((string) $content['payPalOrderId']);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $this->orderRepository->remove($order);

        $this->flashBag->add('success', 'sylius.pay_pal.order_cancelled');

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
