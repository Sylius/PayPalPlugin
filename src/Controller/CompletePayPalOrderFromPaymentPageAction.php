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
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
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
        private ?OrderOwnershipVerifierInterface $orderOwnershipVerifier = null,
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
        if (null === $this->orderOwnershipVerifier) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of "%s" to %s constructor is deprecated and will be required in 3.0.',
                OrderOwnershipVerifierInterface::class,
                self::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $orderId = $request->attributes->getInt('id');

        $order = $this->orderProvider->provideOrderById($orderId);
        if (null === $this->orderOwnershipVerifier) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to verify order ownership.',
                OrderOwnershipVerifierInterface::class,
            ));
        }
        $this->orderOwnershipVerifier->verify($order, $request);

        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        if (null === $payment) {
            return new JsonResponse([], Response::HTTP_CONFLICT);
        }

        $payPalOrderId = (string) ($payment->getDetails()['paypal_order_id'] ?? '');

        try {
            if ($this->paymentAmountVerifier !== null) {
                $this->paymentAmountVerifier->verify($payment);
            } else {
                $this->verify($payment);
            }
        } catch (PaymentAmountMismatchException) {
            $this->paymentStateManager->cancel($payment);

            if (null === $this->orderProcessor) {
                throw new \RuntimeException('Order processor is required to process the order.');
            }
            $this->orderProcessor->process($order);
            $this->orderManager->flush();

            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius_paypal.order_total_changed');

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
