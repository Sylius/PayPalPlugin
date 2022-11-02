<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class CancelPayPalCheckoutPaymentAction
{
    private PaymentProviderInterface $paymentProvider;

    private PaymentStateManagerInterface $paymentStateManager;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        PaymentStateManagerInterface $paymentStateManager
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->paymentStateManager = $paymentStateManager;
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');
        
        /** @var OrderInterface $order */
        $order = $this->orderProvider->provideOrderById($id);
        $payment = $order->getLastPayment();
        
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $flashBag->add('error', 'sylius.pay_pal.something_went_wrong');

        $this->paymentStateManager->cancel($payment);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
