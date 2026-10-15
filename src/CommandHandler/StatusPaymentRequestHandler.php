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
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Command\StatusPaymentRequest;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;

final class StatusPaymentRequestHandler
{
    use FailPaymentRequestTrait;

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

        if (PaymentInterface::STATE_PROCESSING === $payment->getState()) {
            try {
                $this->paymentSettlementProcessor->settle($payment);
            } catch (PayPalApiTimeoutException) {
                $this->failWithReason($paymentRequest, 'PayPal could not be reached to read the order.');

                return;
            }
        }

        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }
}
