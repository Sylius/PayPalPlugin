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
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Command\AuthorizePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PaymentAuthorizationProcessorInterface;
use Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifierInterface;

final class AuthorizePayPalOrderPaymentRequestHandler
{
    use FailThreeDSecureAuthenticationTrait;

    private const INSTRUMENT_DECLINED = 'INSTRUMENT_DECLINED';

    private const UNAPPROVED_ORDER_STATUSES = ['CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED', 'VOIDED'];

    private const SETTLED_PAYMENT_STATES = [PaymentInterface::STATE_AUTHORIZED, PaymentInterface::STATE_COMPLETED];

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly PaymentThreeDSecureVerifierInterface $paymentThreeDSecureVerifier,
        private readonly PaymentAuthorizationProcessorInterface $paymentAuthorizationProcessor,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(AuthorizePayPalOrderPaymentRequest $authorizePayPalOrderPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($authorizePayPalOrderPaymentRequest);

        if (PaymentRequestInterface::STATE_PROCESSING !== $paymentRequest->getState()) {
            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();

        if (in_array($payment->getState(), self::SETTLED_PAYMENT_STATES, true)) {
            $paymentRequest->setResponseData([self::RESPONSE_REASON => 'Another PayPal order has already paid the payment.']);
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);

            return;
        }

        if (!PayPalPaymentDetails::fromPayment($payment)->hasPayPalOrderId()) {
            $this->failWithReason($paymentRequest, 'The payment carries no PayPal order id.');

            return;
        }

        try {
            $this->paymentThreeDSecureVerifier->verify($payment);
            $payPalOrder = $this->paymentAuthorizationProcessor->authorize($payment, sprintf('%s-authorize', (string) $paymentRequest->getId()));
        } catch (ThreeDSecureAuthenticationFailedException $exception) {
            $this->endWithFailedAuthentication($paymentRequest, $payment, $exception->isRetryable());

            return;
        } catch (PayPalApiException $exception) {
            $this->failWithReason($paymentRequest, $exception->hasIssue(self::INSTRUMENT_DECLINED)
                ? 'PayPal declined the payment method. Choose another one.'
                : 'PayPal did not authorize the order.');

            return;
        }

        $authorization = PayPalAuthorization::fromPayPalOrder($payPalOrder);

        if (null === $authorization) {
            in_array($payPalOrder['status'] ?? null, self::UNAPPROVED_ORDER_STATUSES, true)
                ? $this->cancelUnapproved($paymentRequest)
                : $this->failWithReason($paymentRequest, 'PayPal did not authorize the order.');

            return;
        }

        match ($authorization->status()) {
            PayPalAuthorization::STATUS_CREATED => $this->complete($paymentRequest, $payment, PaymentTransitions::TRANSITION_AUTHORIZE),
            PayPalAuthorization::STATUS_PENDING => $this->complete($paymentRequest, $payment, PaymentTransitions::TRANSITION_PROCESS),
            default => $this->refuse($paymentRequest, $payment, $authorization),
        };
    }

    private function cancelUnapproved(PaymentRequestInterface $paymentRequest): void
    {
        $paymentRequest->setResponseData([self::RESPONSE_REASON => 'The payer did not approve the PayPal order.']);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);
    }

    private function complete(PaymentRequestInterface $paymentRequest, PaymentInterface $payment, string $paymentTransition): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, $paymentTransition)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $paymentTransition);
        }

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }

    private function refuse(PaymentRequestInterface $paymentRequest, PaymentInterface $payment, PayPalAuthorization $authorization): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        }

        $this->failWithReason($paymentRequest, sprintf('PayPal reported the authorization as %s.', $authorization->status()));
    }
}
