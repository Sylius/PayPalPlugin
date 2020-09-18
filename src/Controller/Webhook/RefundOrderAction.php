<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class RefundOrderAction
{
    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var PaymentProviderInterface */
    private $paymentProvider;

    /** @var ObjectManager */
    private $paymentManager;

    /** @var PayPalClientInterface */
    private $client;

    /** @var  */

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



        try {
            $payment = $this->paymentProvider->getByPayPalOrderId($this->getPayPalOrderId($request));
        } catch (PaymentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        }

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_REFUND);

        $this->paymentManager->flush();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    private function getPayPalOrderId(Request $request): string
    {
        $content = (array) json_decode((string) $request->getContent(false), true);
        Assert::keyExists($content, 'resource');
        $resource = (array) $content['resource'];
        Assert::keyExists($resource, 'id');

        return (string) $resource['id'];
    }
}
