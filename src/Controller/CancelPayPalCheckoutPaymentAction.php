<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CancelPayPalCheckoutPaymentAction
{
    /** @var PaymentProviderInterface */
    private $paymentProvider;

    /** @var ObjectManager */
    private $objectManager;

    /** @var FactoryInterface */
    private $stateMachineFactory;

    public function __construct(
        PaymentProviderInterface $paymentProvider,
        ObjectManager $objectManager,
        FactoryInterface $stateMachineFactory
    ) {
        $this->paymentProvider = $paymentProvider;
        $this->objectManager = $objectManager;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    public function __invoke(Request $request): Response
    {
        $content = (array) json_decode((string) $request->getContent(false), true);

        $payment = $this->paymentProvider->getByPayPalOrderId((string) $content['payPalOrderId']);

        $paymentStateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $paymentStateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);

        $this->objectManager->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
