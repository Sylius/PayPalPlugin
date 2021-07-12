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
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Webmozart\Assert\Assert;

final class CancelLastPayPalPaymentAction
{
    /** @var PaymentProviderInterface */
    private $paymentProvider;

    /** @var ObjectManager */
    private $objectManager;

    /** @var FlashBag */
    private $flashBag;

    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var OrderProcessorInterface */
    private $orderPaymentProcessor;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        ObjectManager $objectManager,
        FlashBag $flashBag,
        FactoryInterface $stateMachineFactory,
        OrderProcessorInterface $orderPaymentProcessor,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->objectManager = $objectManager;
        $this->flashBag = $flashBag;
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

        if ($payment->getState() === PaymentInterface::STATE_CART || $payment->getState() === PaymentInterface::STATE_PROCESSING) {
            $payment->setState(PaymentInterface::STATE_NEW);
            $paymentStateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);

            $paymentStateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);

            $this->orderPaymentProcessor->process($order);
            $this->objectManager->flush();
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
