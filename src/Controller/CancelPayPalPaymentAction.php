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
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class CancelPayPalPaymentAction
{
    public function __construct(
        private ?PaymentProviderInterface $paymentProvider,
        private ObjectManager $objectManager,
        private RequestStack $flashBagOrRequestStack,
        private StateMachineInterface $stateMachineFactory,
        private OrderProcessorInterface $orderPaymentProcessor,
        private ?PaypalPaymentQueryInterface $paypalPaymentQuery = null,
    ) {
        if (null !== $this->paymentProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                sprintf(
                    'Passing an instance of "%s" as the first argument is deprecated and will be prohibited in 3.0',
                    PaymentProviderInterface::class,
                ),
            );
        }
        if (null === $this->paypalPaymentQuery) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                sprintf(
                    'Not passing an instance of "%s" is deprecated and will be prohibited in 3.0',
                    PaypalPaymentQueryInterface::class,
                ),
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->getPayload();
        $paypalOrderId = $payload->getString('payPalOrderId');

        if (null !== $this->paypalPaymentQuery) {
            $payment = $this->paypalPaymentQuery->getForCancellationByOrderId($paypalOrderId);
        } else {
            $payment = $this->paymentProvider->getByPayPalOrderId($paypalOrderId);
        }

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        if ($this->stateMachineFactory->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
            $this->stateMachineFactory->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

            if ($order->canBeProcessed()) {
                $this->orderPaymentProcessor->process($order);
            }
            $this->objectManager->flush();

            FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)
                ->add('success', 'sylius_paypal.payment_cancelled')
            ;
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
