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

namespace Sylius\PayPalPlugin\CommandHandler;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Api\CaptureAuthorizationApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Command\CaptureAuthorizationPaymentRequest;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;

final class CaptureAuthorizationPaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const RESPONSE_CAPTURE_ID = 'capture_id';

    public const RESPONSE_CAPTURE_STATUS = 'capture_status';

    private const AUTHORIZATION_ALREADY_CAPTURED = 'AUTHORIZATION_ALREADY_CAPTURED';

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly OrderDetailsApiInterface $orderDetailsApi,
        private readonly CaptureAuthorizationApiInterface $captureAuthorizationApi,
        private readonly PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(CaptureAuthorizationPaymentRequest $captureAuthorizationPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($captureAuthorizationPaymentRequest);

        if (!in_array($paymentRequest->getState(), [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING], true)) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $paymentRequest->getMethod();
        if (SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()) {
            $this->failWithReason($paymentRequest, 'Payment method mismatch.');

            return;
        }

        if (PaymentRequestInterface::STATE_NEW === $paymentRequest->getState()) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();

        if (PaymentInterface::STATE_COMPLETED === $payment->getState()) {
            $this->complete($paymentRequest);

            return;
        }

        if (PaymentInterface::STATE_AUTHORIZED !== $payment->getState()) {
            $this->failWithReason($paymentRequest, 'The payment is not authorized.');

            return;
        }

        $details = PayPalPaymentDetails::fromPayment($payment);
        $payPalOrderId = $details->payPalOrderId();
        $authorizationId = $details->authorizationId();
        if (null === $payPalOrderId || null === $authorizationId) {
            $this->failWithReason($paymentRequest, 'The payment carries no PayPal authorization.');

            return;
        }

        try {
            $payPalOrder = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
            $authorization = $payPalOrder->authorization();

            if (null !== $authorization && $authorization->isCapturable()) {
                $this->capture($paymentMethod, $authorizationId, (string) $paymentRequest->getId());
                $payPalOrder = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
                $authorization = $payPalOrder->authorization();
            }
        } catch (PayPalApiException $exception) {
            $this->failWithReason($paymentRequest, null === $exception->getStatusCode()
                ? 'PayPal could not be reached to capture the payment.'
                : sprintf('PayPal rejected the capture: %s', $exception->getDescription()));

            return;
        }

        if (null !== $authorization) {
            $payment->setDetails(PayPalPaymentDetails::fromPayment($payment)->withAuthorization($authorization)->toArray());
        }

        $capture = $payPalOrder->capture();
        if (null === $capture) {
            $this->failWithReason($paymentRequest, sprintf('PayPal reported the authorization as %s.', $authorization?->status() ?? 'missing'));

            return;
        }

        $this->paymentSettlementProcessor->settle($payment, $payPalOrder);

        $paymentRequest->setResponseData([
            self::RESPONSE_CAPTURE_ID => $capture->id(),
            self::RESPONSE_CAPTURE_STATUS => $capture->status(),
        ]);

        match ($capture->status()) {
            PayPalCapture::STATUS_COMPLETED => $this->complete($paymentRequest),
            PayPalCapture::STATUS_PENDING => null,
            default => $this->failWithReason($paymentRequest, sprintf('PayPal reported the capture as %s.', $capture->status())),
        };
    }

    private function complete(PaymentRequestInterface $paymentRequest): void
    {
        if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
        }
    }

    private function capture(PaymentMethodInterface $paymentMethod, string $authorizationId, string $requestId): void
    {
        try {
            $this->captureAuthorizationApi->capture($paymentMethod, $authorizationId, $requestId);
        } catch (PayPalApiException $exception) {
            if (!$exception->hasIssue(self::AUTHORIZATION_ALREADY_CAPTURED)) {
                throw $exception;
            }
        }
    }
}
