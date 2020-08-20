<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

final class CancelPayPalPaymentAction
{
    /** @var PaymentProviderInterface */
    private $paymentProvider;

    /** @var ObjectManager */
    private $manager;

    /** @var FlashBag */
    private $flashBag;

    /** @var FactoryInterface */
    private $stateMachine;

    /** @var OrderProcessorInterface */
    private $paymentProcessor;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        ObjectManager $manager,
        FlashBag $flashBag,
        FactoryInterface $stateMachine,
        OrderProcessorInterface $paymentProcessor
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->manager = $manager;
        $this->flashBag = $flashBag;
        $this->stateMachine = $stateMachine;
        $this->paymentProcessor = $paymentProcessor;
    }

    public function __invoke(Request $request): Response
    {
        $content = (array) json_decode((string) $request->getContent(false), true);

        $payment = $this->paymentProvider->getByPayPalOrderId((string) $content['payPalOrderId']);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $paymentStateMachine = $this->stateMachine->get($payment, PaymentTransitions::GRAPH);
        $paymentStateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);

        $this->paymentProcessor->process($order);
        $this->manager->flush();

        $this->flashBag->add('success', 'sylius.pay_pal.payment_cancel');

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
