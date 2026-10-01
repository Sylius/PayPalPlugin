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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CancelLastPayPalPaymentAction
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private ObjectManager $objectManager,
        private StateMachineInterface $stateMachineFactory,
        private OrderProcessorInterface $orderPaymentProcessor,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        trigger_deprecation(
            'sylius/paypal-plugin',
            '2.1',
            'The "sylius_paypal_shop_cancel_last_payment" route is deprecated and will be removed in 3.0,' .
            ' along with the legacy "pay_with_paypal" checkout page it exists to support.',
        );

        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneByTokenValue((string) $request->attributes->get('token'));

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();

        if (!$this->stateMachineFactory->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->stateMachineFactory->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

        /** @var PaymentInterface $lastPayment */
        $lastPayment = $order->getLastPayment();
        if ($lastPayment->getState() === PaymentInterface::STATE_NEW) {
            $this->objectManager->flush();

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->orderPaymentProcessor->process($order);
        $this->objectManager->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
