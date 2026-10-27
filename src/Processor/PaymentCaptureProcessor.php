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
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;

final readonly class PaymentCaptureProcessor implements PaymentCaptureProcessorInterface
{
    private const ISSUES_LEAVING_THE_ORDER_TO_READ = ['ORDER_ALREADY_CAPTURED', 'ORDER_NOT_APPROVED', 'PAYER_ACTION_REQUIRED'];

    public function __construct(
        private ApprovedPayPalOrderCheckerInterface $approvedPayPalOrderChecker,
        private ApprovedPayPalOrderUpdaterInterface $approvedPayPalOrderUpdater,
        private CompleteOrderApiInterface $completeOrderApi,
        private OrderDetailsApiInterface $orderDetailsApi,
    ) {
    }

    public function capture(PaymentInterface $payment, ?string $requestId = null): PayPalOrderDetails
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $details = PayPalPaymentDetails::fromPayment($payment);
        $payPalOrderId = (string) $details->payPalOrderId();

        $orderDetails = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);

        if ($this->approvedPayPalOrderChecker->isApproved($orderDetails, $details->paymentSource())) {
            $this->captureApprovedOrder($payment, $paymentMethod, $payPalOrderId, $requestId);
            $orderDetails = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
        }

        $capture = $orderDetails->capture();
        if (null === $capture) {
            return $orderDetails;
        }

        $capturedDetails = PayPalPaymentDetails::create()
            ->withStatus($orderDetails->isStatus('COMPLETED') ? PayPalPaymentStatus::Completed : PayPalPaymentStatus::Processing)
            ->withPayPalOrderId($orderDetails->id())
            ->withReferenceId((string) $orderDetails->referenceId())
            ->withPaymentSource($details->paymentSource())
            ->withSupersededPayPalOrders($details->supersededPayPalOrders())
        ;
        if (null !== $capture->id()) {
            $capturedDetails = $capturedDetails->withTransactionId($capture->id());
        }

        $payment->setDetails($capturedDetails->toArray());

        return $orderDetails;
    }

    private function captureApprovedOrder(
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        string $payPalOrderId,
        ?string $requestId,
    ): void {
        $this->approvedPayPalOrderUpdater->update($payment);

        try {
            $this->completeOrderApi->complete($paymentMethod, $payPalOrderId, $requestId);
        } catch (PayPalApiConnectionException|PayPalApiServerException) {
        } catch (PayPalApiRequestException $exception) {
            if ([] === array_intersect($exception->getIssues(), self::ISSUES_LEAVING_THE_ORDER_TO_READ)) {
                throw $exception;
            }
        }
    }
}
