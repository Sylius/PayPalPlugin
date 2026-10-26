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
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\VoidAuthorizationApiInterface;
use Sylius\PayPalPlugin\Command\VoidAuthorizationPaymentRequest;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final class VoidAuthorizationPaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly OrderDetailsApiInterface $orderDetailsApi,
        private readonly VoidAuthorizationApiInterface $voidAuthorizationApi,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(VoidAuthorizationPaymentRequest $voidAuthorizationPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($voidAuthorizationPaymentRequest);

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
        $details = PayPalPaymentDetails::fromPayment($payment);

        if (PayPalAuthorization::isStatusReleased($details->authorizationStatus())) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);

            return;
        }

        $payPalOrderId = $details->payPalOrderId();
        $authorizationId = $details->authorizationId();
        if (null === $payPalOrderId || null === $authorizationId) {
            $this->failWithReason($paymentRequest, 'The payment carries no PayPal authorization.');

            return;
        }

        try {
            $authorization = PayPalAuthorization::fromPayPalOrder($this->orderDetailsApi->get($paymentMethod, $payPalOrderId));
        } catch (PayPalApiConnectionException|PayPalApiServerException $exception) {
            throw $exception;
        } catch (PayPalApiException $exception) {
            $this->failWithReason($paymentRequest, sprintf('PayPal rejected reading the order: %s', $exception->getDescription()));

            return;
        }

        if (null === $authorization) {
            $this->failWithReason($paymentRequest, 'The PayPal order carries no authorization.');

            return;
        }

        if ($authorization->isVoidable()) {
            try {
                $authorization = $this->voidAuthorizationApi->void($paymentMethod, $authorizationId, (string) $paymentRequest->getId())
                    ?? new PayPalAuthorization(PayPalAuthorization::STATUS_VOIDED, $authorizationId);
            } catch (PayPalApiConnectionException|PayPalApiServerException $exception) {
                throw $exception;
            } catch (PayPalApiException $exception) {
                $this->failWithReason($paymentRequest, sprintf('PayPal rejected the void: %s', $exception->getDescription()));

                return;
            }
        }

        $payment->setDetails($details->withAuthorization($authorization)->toArray());

        if (!$authorization->isReleased()) {
            $this->failWithReason($paymentRequest, sprintf('PayPal reported the authorization as %s.', $authorization->status()));

            return;
        }

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }
}
