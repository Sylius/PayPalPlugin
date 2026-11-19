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

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Command\AuthorizePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PaymentAuthorizationProcessorInterface;
use Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifierInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

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
        private readonly EntityManagerInterface $paymentManager,
        private readonly EntityManagerInterface $paymentRequestManager,
        StateMachineInterface $stateMachine,
        private readonly ?MessageBusInterface $eventBus = null,
    ) {
        $this->stateMachine = $stateMachine;

        if (null === $this->eventBus) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '3.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 4.0.',
                MessageBusInterface::class,
                self::class,
            );
        }
    }

    public function __invoke(AuthorizePayPalOrderPaymentRequest $authorizePayPalOrderPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($authorizePayPalOrderPaymentRequest);
        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        $this->paymentManager->refresh($payment, LockMode::PESSIMISTIC_WRITE);
        $this->paymentRequestManager->refresh($paymentRequest, LockMode::PESSIMISTIC_WRITE);

        if (PaymentRequestInterface::STATE_PROCESSING !== $paymentRequest->getState()) {
            return;
        }

        if (in_array($payment->getState(), self::SETTLED_PAYMENT_STATES, true)) {
            if ($this->isPaidByItsPayPalOrder($paymentRequest, $payment)) {
                $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);

                return;
            }

            $paymentRequest->setResponseData([self::RESPONSE_REASON => 'Another PayPal order has already paid the payment.']);
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL);

            return;
        }

        $details = PayPalPaymentDetails::fromPayment($payment);
        if (!$details->hasPayPalOrderId()) {
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
        $this->announceRevealedVaultToken($payment, $details->vaultId());

        $authorization = $payPalOrder->authorization();

        if (null === $authorization) {
            in_array($payPalOrder->status(), self::UNAPPROVED_ORDER_STATUSES, true)
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

    private function announceRevealedVaultToken(PaymentInterface $payment, ?string $knownVaultId): void
    {
        $vaultId = PayPalPaymentDetails::fromPayment($payment)->vaultId();
        if (null === $vaultId || $knownVaultId === $vaultId) {
            return;
        }

        $this->eventBus?->dispatch(
            new PayPalPaymentTokenCreated($payment->getId(), $vaultId),
            [new DispatchAfterCurrentBusStamp()],
        );
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

    private function isPaidByItsPayPalOrder(PaymentRequestInterface $paymentRequest, PaymentInterface $payment): bool
    {
        $payPalOrderId = $paymentRequest->getResponseData()[CreatePayPalOrderPaymentRequestHandler::RESPONSE_PAYPAL_ORDER_ID] ?? null;

        return is_string($payPalOrderId) && $payPalOrderId === PayPalPaymentDetails::fromPayment($payment)->payPalOrderId();
    }
}
