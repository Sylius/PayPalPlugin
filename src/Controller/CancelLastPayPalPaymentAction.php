<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CancelLastPayPalPaymentAction
{
    /** @var ObjectManager */
    private $objectManager;

    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var OrderProcessorInterface */
    private $orderPaymentProcessor;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(
        ObjectManager $objectManager,
        FactoryInterface $stateMachineFactory,
        OrderProcessorInterface $orderPaymentProcessor,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->objectManager = $objectManager;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->orderPaymentProcessor = $orderPaymentProcessor;
        $this->orderRepository = $orderRepository;
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneByTokenValue((string) $request->attributes->get('token'));

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();

        $paymentStateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $paymentStateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);

        $this->orderPaymentProcessor->process($order);
        $this->objectManager->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
