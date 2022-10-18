<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

final class CancelPayPalPaymentAction
{
    private PaymentProviderInterface $paymentProvider;

    private ObjectManager $objectManager;

    private FlashBag|RequestStack $flashBagOrRequestStack;

    private FactoryInterface $stateMachineFactory;

    private OrderProcessorInterface $orderPaymentProcessor;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        ObjectManager $objectManager,
        FlashBag|RequestStack $flashBagOrRequestStack,
        FactoryInterface $stateMachineFactory,
        OrderProcessorInterface $orderPaymentProcessor
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->objectManager = $objectManager;
        $this->flashBagOrRequestStack = $flashBagOrRequestStack;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->orderPaymentProcessor = $orderPaymentProcessor;
    }

    public function __invoke(Request $request): Response
    {
        $content = (array) json_decode((string) $request->getContent(false), true);

        $payment = $this->paymentProvider->getByPayPalOrderId((string) $content['payPalOrderId']);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $paymentStateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $paymentStateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);

        $this->orderPaymentProcessor->process($order);
        $this->objectManager->flush();

        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)
            ->add('success', 'sylius.pay_pal.payment_cancelled')
        ;

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
