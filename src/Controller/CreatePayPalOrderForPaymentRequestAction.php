<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Bundle\PaymentBundle\Announcer\PaymentRequestAnnouncerInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\CommandHandler\CreatePayPalOrderPaymentRequestHandler;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CreatePayPalOrderForPaymentRequestAction
{
    /**
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     * @param PaymentRequestFactoryInterface<PaymentRequestInterface> $paymentRequestFactory
     */
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private PaymentRequestAnnouncerInterface $paymentRequestAnnouncer,
        private StateMachineInterface $stateMachine,
        private UrlProviderInterface $paymentRequestPayUrlProvider,
    ) {
    }

    public function __invoke(Request $request, string $hash): Response
    {
        $paymentRequest = $this->paymentRequestRepository->find($hash);
        if (null === $paymentRequest || !$this->isPayPalCapture($paymentRequest)) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        if (PaymentInterface::STATE_NEW !== $paymentRequest->getPayment()->getState()) {
            return new JsonResponse([], Response::HTTP_CONFLICT);
        }

        $paymentSource = $this->paymentSource($request);
        if (null === $paymentSource) {
            return new JsonResponse([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState()) {
            if ($paymentSource === $this->requestedPaymentSource($paymentRequest)) {
                return $this->respond($paymentRequest);
            }

            $paymentRequest = $this->startAnotherAttempt($paymentRequest);
        } elseif (PaymentRequestInterface::STATE_NEW !== $paymentRequest->getState()) {
            return new JsonResponse([], Response::HTTP_CONFLICT);
        }

        $paymentRequest->setPayload([CreatePayPalOrderPaymentRequestHandler::PAYLOAD_PAYMENT_SOURCE => $paymentSource]);
        $this->paymentRequestRepository->add($paymentRequest);

        $this->paymentRequestAnnouncer->dispatchPaymentRequestCommand($paymentRequest);

        return $this->respond($paymentRequest);
    }

    private function isPayPalCapture(PaymentRequestInterface $paymentRequest): bool
    {
        /** @var GatewayConfigInterface|null $gatewayConfig */
        $gatewayConfig = $paymentRequest->getMethod()->getGatewayConfig();

        return
            PaymentRequestInterface::ACTION_CAPTURE === $paymentRequest->getAction() &&
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $gatewayConfig?->getFactoryName()
        ;
    }

    private function paymentSource(Request $request): ?string
    {
        $payload = json_decode($request->getContent(), true);
        $paymentSource = is_array($payload) ? ($payload['paymentSource'] ?? null) : null;

        if (null === $paymentSource) {
            return PayPalPaymentSourceProviderInterface::PAYPAL;
        }

        return is_string($paymentSource) ? $paymentSource : null;
    }

    private function requestedPaymentSource(PaymentRequestInterface $paymentRequest): mixed
    {
        $payload = $paymentRequest->getPayload();

        return is_array($payload) ? ($payload[CreatePayPalOrderPaymentRequestHandler::PAYLOAD_PAYMENT_SOURCE] ?? null) : null;
    }

    private function startAnotherAttempt(PaymentRequestInterface $paymentRequest): PaymentRequestInterface
    {
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);

        $anotherAttempt = $this->paymentRequestFactory->createFromPaymentRequest($paymentRequest);
        $anotherAttempt->setAction(PaymentRequestInterface::ACTION_CAPTURE);

        return $anotherAttempt;
    }

    private function respond(PaymentRequestInterface $paymentRequest): JsonResponse
    {
        return new JsonResponse(
            [
                'hash' => $paymentRequest->getId(),
                'approve_url' => $this->paymentRequestPayUrlProvider->getUrl($paymentRequest),
            ] + $paymentRequest->getResponseData(),
            PaymentRequestInterface::STATE_FAILED === $paymentRequest->getState() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }
}
