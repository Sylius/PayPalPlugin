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
use Sylius\PayPalPlugin\Api\ChargeVaultedOrderApiInterface;
use Sylius\PayPalPlugin\Command\ChargeVaultedPaymentRequest;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class ChargeVaultedPaymentRequestHandler
{
    use FailPaymentRequestTrait;

    public const RESPONSE_ISSUES = 'issues';

    public function __construct(
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly ChargeVaultedOrderApiInterface $chargeVaultedOrderApi,
        private readonly UuidProviderInterface $uuidProvider,
        private readonly PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        StateMachineInterface $stateMachine,
    ) {
        $this->stateMachine = $stateMachine;
    }

    /**
     * @throws PayPalApiConnectionException|PayPalApiServerException
     */
    public function __invoke(ChargeVaultedPaymentRequest $chargeVaultedPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($chargeVaultedPaymentRequest);

        if (PaymentRequestInterface::STATE_PROCESSING !== $paymentRequest->getState()) {
            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        $details = PayPalPaymentDetails::fromPayment($payment);

        $vaultId = $details->vaultId();
        if (null === $vaultId || '' === $vaultId) {
            $this->failPayment($payment);
            $this->failWithReason($paymentRequest, 'The payment carries no vaulted PayPal wallet.');

            return;
        }

        $referenceId = $this->uuidProvider->provide();

        try {
            $payPalOrder = $this->chargeVaultedOrderApi->charge($payment, $vaultId, $referenceId, (string) $paymentRequest->getId());
        } catch (PayPalApiConnectionException|PayPalApiServerException $exception) {
            throw $exception;
        } catch (PayPalApiException $exception) {
            $this->failPayment($payment);
            $this->failWithIssues($paymentRequest, 'PayPal refused to charge the vaulted wallet.', $exception->getIssues());

            return;
        }

        $payment->setDetails($details
            ->withStatus(PayPalPaymentStatus::Processing)
            ->withPayPalOrderId((string) ($payPalOrder['id'] ?? ''))
            ->withReferenceId($referenceId)
            ->withAmount((int) $payment->getAmount())
            ->withPaymentSource(PayPalPaymentSourceProviderInterface::PAYPAL)
            ->toArray());

        $capture = PayPalCapture::fromPayPalOrder($payPalOrder);
        if (null === $capture) {
            $this->failPayment($payment);
            $this->failWithReason($paymentRequest, 'PayPal did not capture the order.');

            return;
        }

        $this->paymentSettlementProcessor->settle($payment, $payPalOrder);

        match ($capture->status()) {
            PayPalCapture::STATUS_COMPLETED => $this->complete($paymentRequest),
            PayPalCapture::STATUS_PENDING => $this->awaitCapture($paymentRequest, $payment),
            default => $this->failWithReason($paymentRequest, sprintf('PayPal reported the capture as %s.', $capture->status())),
        };
    }

    private function awaitCapture(PaymentRequestInterface $paymentRequest, PaymentInterface $payment): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS);
        }

        $this->complete($paymentRequest);
    }

    private function complete(PaymentRequestInterface $paymentRequest): void
    {
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
    }

    private function failPayment(PaymentInterface $payment): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        }
    }

    /** @param list<string> $issues */
    private function failWithIssues(PaymentRequestInterface $paymentRequest, string $reason, array $issues): void
    {
        $paymentRequest->setResponseData([self::RESPONSE_REASON => $reason, self::RESPONSE_ISSUES => $issues]);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);
    }
}
