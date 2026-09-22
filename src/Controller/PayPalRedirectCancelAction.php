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
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalRedirectCancelAction
{
    public function __construct(
        private OrderProviderInterface $orderProvider,
        private PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        private StateMachineInterface $stateMachine,
        private OrderProcessorInterface $orderPaymentProcessor,
        private ObjectManager $objectManager,
        private UrlGeneratorInterface $router,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->orderProvider->provideOrderByToken((string) $request->attributes->get('token'));

        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        if (null !== $payment) {
            $this->paymentSettlementProcessor->settle($payment);
        }

        if (null !== $payment && PaymentInterface::STATE_COMPLETED === $payment->getState()) {
            return new RedirectResponse($this->router->generate('sylius_shop_order_thank_you'));
        }

        if (null !== $payment && PaymentInterface::STATE_PROCESSING === $payment->getState()) {
            $this->cancel($payment, $order);
        }

        FlashBagProvider::getFlashBag($this->requestStack)->add('info', 'sylius_paypal.payment_cancelled');

        return new RedirectResponse($this->payPalPageUrl($order));
    }

    private function cancel(PaymentInterface $payment, OrderInterface $order): void
    {
        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
            return;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

        $this->orderPaymentProcessor->process($order);
        $this->objectManager->flush();
    }

    private function payPalPageUrl(OrderInterface $order): string
    {
        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);
        if (null === $payment) {
            return $this->router->generate('sylius_shop_order_show', ['tokenValue' => $order->getTokenValue()]);
        }

        return $this->router->generate('sylius_paypal_shop_pay_with_paypal_form', [
            'orderToken' => $order->getTokenValue(),
            'paymentId' => $payment->getId(),
        ]);
    }
}
