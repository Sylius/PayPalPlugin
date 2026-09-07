<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use GuzzleHttp\Exception\GuzzleException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final readonly class CreatePayPalOrderFromPaymentPageAction
{
    public function __construct(
        private StateMachineInterface $stateMachineFactory,
        private PaymentStateManagerInterface $paymentStateManager,
        private OrderProviderInterface $orderProvider,
        private CapturePaymentResolverInterface $capturePaymentResolver,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');

        $order = $this->orderProvider->provideOrderById($id);

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        $this->stateMachineFactory->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        try {
            $this->capturePaymentResolver->resolve($payment);
        } catch (GuzzleException $exception) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius_paypal.something_went_wrong');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->paymentStateManager->create($payment);
        $this->paymentStateManager->process($payment);

        $payPalOrderId = $payment->getDetails()['paypal_order_id'];

        return new JsonResponse([
            'id' => $order->getId(),
            'orderId' => $payPalOrderId,
            'order_id' => $payPalOrderId, // BC with 2.0. Deprecated in 2.1; use "orderId" instead.
            'status' => $payment->getState(),
        ]);
    }
}
