<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Abstraction\StateMachine\WinzouStateMachineAdapter;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CancelLastPayPalPaymentAction
{
    public function __construct(
        private readonly ObjectManager $objectManager,
        private readonly FactoryInterface|StateMachineInterface $stateMachineFactory,
        private readonly OrderProcessorInterface $orderPaymentProcessor,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                sprintf(
                    'Passing an instance of "%s" as the second argument is deprecated and will be prohibited in 2.0. Use "%s" instead.',
                    FactoryInterface::class,
                    StateMachineInterface::class,
                ),
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneByTokenValue((string) $request->attributes->get('token'));

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();

        $this->getStateMachine()->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

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

    private function getStateMachine(): StateMachineInterface
    {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            return new WinzouStateMachineAdapter($this->stateMachineFactory);
        }

        return $this->stateMachineFactory;
    }
}
