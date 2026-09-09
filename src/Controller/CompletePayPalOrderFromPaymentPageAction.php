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

use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CompletePayPalOrderFromPaymentPageAction
{
    public function __construct(
        private PaymentStateManagerInterface $paymentStateManager,
        private UrlGeneratorInterface $router,
        private OrderProviderInterface $orderProvider,
        private StateMachineInterface $stateMachine,
        private ObjectManager $orderManager,
        private ?PaymentAmountVerifierInterface $paymentAmountVerifier = null,
        private ?OrderProcessorInterface $orderProcessor = null,
    ) {
        if (null === $this->paymentAmountVerifier) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing an instance of "%s" as the fifth argument is deprecated and will be prohibited in 3.0.',
                PaymentAmountVerifierInterface::class,
            );
        }
        if (null === $this->orderProcessor) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing an instance of "%s" as the sixth argument is deprecated and will be prohibited in 3.0.',
                OrderProcessorInterface::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $tokenValue = (string) $request->attributes->get('tokenValue');

        $order = $this->orderProvider->provideCartByToken($tokenValue);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        /** @var string $payPalOrderId */
        $payPalOrderId = $payment->getDetails()['paypal_order_id'] ?? '';

        try {
            if ($this->paymentAmountVerifier !== null) {
                $this->paymentAmountVerifier->verify($payment);
            } else {
                $this->verify($payment);
            }
        } catch (PaymentAmountMismatchException) {
            $this->paymentStateManager->cancel($payment);
            $order->removePayment($payment);

            if (null === $this->orderProcessor) {
                throw new \RuntimeException('Order processor is required to process the order.');
            }
            $this->orderProcessor->process($order);

            return new JsonResponse([
                'orderId' => $payPalOrderId,
                'status' => $payment->getState(),
                'return_url' => $this->router->generate('sylius_shop_checkout_complete', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        $this->paymentStateManager->complete($payment);
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->orderManager->flush();

        $request->getSession()->set('sylius_order_id', $order->getId());

        return new JsonResponse([
            'orderId' => $payPalOrderId,
            'status' => $payment->getState(),
            'return_url' => $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    private function verify(PaymentInterface $payment): void
    {
        $totalAmount = $this->getTotalPaymentAmountFromPaypal($payment);

        if ($payment->getOrder()->getTotal() !== $totalAmount) {
            throw new PaymentAmountMismatchException();
        }
    }

    private function getTotalPaymentAmountFromPaypal(PaymentInterface $payment): int
    {
        $details = $payment->getDetails();

        return $details['payment_amount'] ?? 0;
    }
}
