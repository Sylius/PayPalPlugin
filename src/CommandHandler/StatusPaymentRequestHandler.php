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
use Sylius\PayPalPlugin\Command\StatusPaymentRequest;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;

final class StatusPaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const PAYLOAD_SETTLE_AUTHORIZED = 'settle_authorized';

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    public function __invoke(StatusPaymentRequest $statusPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($statusPaymentRequest);

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();

        if (!PayPalPaymentDetails::fromPayment($payment)->hasPayPalOrderId()) {
            $this->failWithReason($paymentRequest, 'The payment carries no PayPal order id.');

            return;
        }

        if ($this->shouldSettle($payment, $paymentRequest)) {
            try {
                $this->paymentSettlementProcessor->settle($payment);
            } catch (PayPalApiConnectionException|PayPalApiServerException) {
                $this->failWithReason($paymentRequest, 'PayPal could not be reached to read the order.');

                return;
            } catch (PayPalApiException) {
                $this->failWithReason($paymentRequest, 'PayPal could not return the order.');

                return;
            }
        }

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }

    private function shouldSettle(PaymentInterface $payment, PaymentRequestInterface $paymentRequest): bool
    {
        if (PaymentInterface::STATE_PROCESSING === $payment->getState()) {
            return true;
        }

        $payload = $paymentRequest->getPayload();

        return
            PaymentInterface::STATE_AUTHORIZED === $payment->getState() &&
            is_array($payload) &&
            true === ($payload[self::PAYLOAD_SETTLE_AUTHORIZED] ?? false)
        ;
    }
}
