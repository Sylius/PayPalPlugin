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

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderCheckerInterface;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Model\PayPalVault;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class PaymentCaptureProcessor implements PaymentCaptureProcessorInterface
{
    private const ISSUES_LEAVING_THE_ORDER_TO_READ = ['ORDER_ALREADY_CAPTURED', 'ORDER_NOT_APPROVED', 'PAYER_ACTION_REQUIRED'];

    public function __construct(
        private ApprovedPayPalOrderCheckerInterface $approvedPayPalOrderChecker,
        private ApprovedPayPalOrderUpdaterInterface $approvedPayPalOrderUpdater,
        private CompleteOrderApiInterface $completeOrderApi,
        private OrderDetailsApiInterface $orderDetailsApi,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function capture(PaymentInterface $payment, ?string $requestId = null): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $details = PayPalPaymentDetails::fromPayment($payment);
        $payPalOrderId = (string) $details->payPalOrderId();

        $orderDetails = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);

        $captureResponse = [];
        if ($this->approvedPayPalOrderChecker->isApproved($orderDetails, $details->paymentSource())) {
            $captureResponse = $this->captureApprovedOrder($payment, $paymentMethod, $payPalOrderId, $requestId);
            $orderDetails = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
        }

        if (null === PayPalCapture::fromPayPalOrder($orderDetails)) {
            return $orderDetails;
        }

        $capturedDetails = PayPalPaymentDetails::create()
            ->withStatus('COMPLETED' === $orderDetails['status'] ? PayPalPaymentStatus::Completed : PayPalPaymentStatus::Processing)
            ->withPayPalOrderId((string) $orderDetails['id'])
            ->withReferenceId((string) $orderDetails['purchase_units'][0]['reference_id'])
            ->withPaymentSource($details->paymentSource())
            ->withSupersededPayPalOrders($details->supersededPayPalOrders())
        ;
        if (isset($orderDetails['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $capturedDetails = $capturedDetails->withTransactionId(
                (string) $orderDetails['purchase_units'][0]['payments']['captures'][0]['id'],
            );
        }

        $vault = PayPalVault::fromPayPalOrder($captureResponse) ?? PayPalVault::fromPayPalOrder($orderDetails);
        $knownVault = $details->vault();
        if (null === $vault?->id() && null !== $knownVault?->id()) {
            $vault = $knownVault;
        }
        if (null !== $vault) {
            $capturedDetails = $capturedDetails->withVault($vault);
        }

        $payment->setDetails($capturedDetails->toArray());

        $vaultId = $vault?->id();
        if (null !== $vaultId && null === $knownVault?->id()) {
            $this->eventDispatcher->dispatch(new PayPalPaymentTokenCreated($payment, $vaultId));
        }

        return $orderDetails;
    }

    /** @return array<string, mixed> */
    private function captureApprovedOrder(
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        string $payPalOrderId,
        ?string $requestId,
    ): array {
        $this->approvedPayPalOrderUpdater->update($payment);

        try {
            return $this->completeOrderApi->complete($paymentMethod, $payPalOrderId, $requestId);
        } catch (PayPalApiConnectionException|PayPalApiServerException) {
        } catch (PayPalApiRequestException $exception) {
            if ([] === array_intersect($exception->getIssues(), self::ISSUES_LEAVING_THE_ORDER_TO_READ)) {
                throw $exception;
            }
        }

        return [];
    }
}
