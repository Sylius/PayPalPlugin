<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Abstraction\StateMachine\WinzouStateMachineAdapter;
use Sylius\Component\Payment\PaymentTransitions;
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
    public function __construct(
        private readonly FactoryInterface|StateMachineInterface $stateMachineFactory,
        private readonly PaymentProviderInterface $paymentProvider,
        private readonly ObjectManager $paymentManager,
        private readonly PayPalRefundDataProviderInterface $payPalRefundDataProvider,
    ) {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                message: sprintf(
                    'Passing an instance of "%s" as the first argument is deprecated and will be prohibited in 2.0. Use "%s" instead.',
                    FactoryInterface::class,
                    StateMachineInterface::class,
                ),
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $refundData = $this->payPalRefundDataProvider->provide($this->getPayPalPaymentUrl($request));

        try {
            $payment = $this->paymentProvider->getByPayPalOrderId((string) $refundData['id']);
        } catch (PaymentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        }

        $stateMachine = $this->getStateMachine();

        if ($stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)) {
            $stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        }

        $this->paymentManager->flush();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    private function getPayPalPaymentUrl(Request $request): string
    {
        /**
         * @var string $content
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

    private function getStateMachine(): StateMachineInterface
    {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            return new WinzouStateMachineAdapter($this->stateMachineFactory);
        }

        return $this->stateMachineFactory;
    }
}
