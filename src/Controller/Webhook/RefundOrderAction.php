<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RefundOrderAction
{
    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var PaymentProviderInterface */
    private $paymentProvider;

    /** @var ObjectManager */
    private $paymentManager;

    public function __construct(
        FactoryInterface $stateMachineFactory,
        PaymentProviderInterface $paymentProvider,
        ObjectManager $paymentManager
    ) {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentProvider = $paymentProvider;
        $this->paymentManager = $paymentManager;
    }

    public function __invoke(Request $request): Response
    {
        $content = json_decode($request->getContent(false), true);

        try {
            $payment = $this->paymentProvider->getByPayPalOrderId($content['resource']['id']);
        } catch (PaymentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        }

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_REFUND);

        $this->paymentManager->flush();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
