<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

final class CancelPayPalOrderAction
{
    private PaymentProviderInterface $paymentProvider;

    private OrderRepositoryInterface $orderRepository;

    private FlashBag|RequestStack $flashBagOrRequestStack;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        OrderRepositoryInterface $orderRepository,
        FlashBag|RequestStack $flashBagOrRequestStack
    ) {
        if ($flashBagOrRequestStack instanceof FlashBag) {
            trigger_deprecation('sylius/paypal-plugin', '1.5', sprintf('Passing an instance of %s as constructor argument for %s is deprecated as of PayPalPlugin 1.5 and will be removed in 2.0. Pass an instance of %s instead.', FlashBag::class, self::class, RequestStack::class));
        }

        $this->paymentProvider = $paymentProvider;
        $this->orderRepository = $orderRepository;
        $this->flashBagOrRequestStack = $flashBagOrRequestStack;
    }

    public function __invoke(Request $request): Response
    {
        /**
         * @var string $content
         * @psalm-suppress UnnecessaryVarAnnotation
         */
        $content = $request->getContent();

        $content = (array) json_decode($content, true);

        $payment = $this->paymentProvider->getByPayPalOrderId((string) $content['payPalOrderId']);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $this->orderRepository->remove($order);

        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)->add('success', 'sylius.pay_pal.order_cancelled');

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
