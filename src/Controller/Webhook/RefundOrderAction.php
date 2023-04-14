<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class RefundOrderAction
{
    private FactoryInterface $stateMachineFactory;

    private PaymentProviderInterface $paymentProvider;

    private ObjectManager $paymentManager;

    private PayPalRefundDataProviderInterface $payPalRefundDataProvider;

    public function __construct(
        FactoryInterface $stateMachineFactory,
        PaymentProviderInterface $paymentProvider,
        ObjectManager $paymentManager,
        PayPalRefundDataProviderInterface $payPalRefundDataProvider
    ) {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentProvider = $paymentProvider;
        $this->paymentManager = $paymentManager;
        $this->payPalRefundDataProvider = $payPalRefundDataProvider;
    }

    public function __invoke(Request $request): Response
    {
        $refundData = $this->payPalRefundDataProvider->provide($this->getPayPalPaymentUrl($request));

        try {
            $payment = $this->paymentProvider->getByPayPalOrderId((string) $refundData['id']);
        } catch (PaymentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        }

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        if ($stateMachine->can(PaymentTransitions::TRANSITION_REFUND)) {
            $stateMachine->apply(PaymentTransitions::TRANSITION_REFUND);
        }

        $this->paymentManager->flush();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    private function getPayPalPaymentUrl(Request $request): string
    {
        /**
         * @var string $content
         * @psalm-suppress UnnecessaryVarAnnotation
         */
        $content = $request->getContent();

        $content = (array) json_decode($content, true);
        Assert::keyExists($content, 'resource');
        $resource = (array) $content['resource'];
        Assert::keyExists($resource, 'links');

        /** @var string[] $link */
        foreach ($resource['links'] as $link) {
            if ($link['rel'] === 'up') {
                return $link['href'];
            }
        }

        throw new PayPalWrongDataException();
    }
}
