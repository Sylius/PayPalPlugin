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
use Sylius\PayPalPlugin\Api\AuthorizeOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderCheckerInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;

final readonly class PaymentAuthorizationProcessor implements PaymentAuthorizationProcessorInterface
{
    private const ISSUES_LEAVING_THE_ORDER_TO_READ = ['ORDER_ALREADY_AUTHORIZED', 'ORDER_NOT_APPROVED', 'PAYER_ACTION_REQUIRED'];

    public function __construct(
        private ApprovedPayPalOrderCheckerInterface $approvedPayPalOrderChecker,
        private ApprovedPayPalOrderUpdaterInterface $approvedPayPalOrderUpdater,
        private AuthorizeOrderApiInterface $authorizeOrderApi,
        private OrderDetailsApiInterface $orderDetailsApi,
    ) {
    }

    public function authorize(PaymentInterface $payment, ?string $requestId = null): PayPalOrderDetails
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $details = PayPalPaymentDetails::fromPayment($payment);
        $payPalOrderId = (string) $details->payPalOrderId();

        $orderDetails = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);

        if ($this->approvedPayPalOrderChecker->isApproved($orderDetails, $details->paymentSource())) {
            $this->authorizeApprovedOrder($payment, $paymentMethod, $payPalOrderId, $requestId);
            $orderDetails = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
        }

        $authorization = $orderDetails->authorization();
        if (null === $authorization) {
            return $orderDetails;
        }

        $payment->setDetails(
            PayPalPaymentDetails::fromPayment($payment)
                ->withStatus($authorization->isStatus(PayPalAuthorization::STATUS_CREATED) ? PayPalPaymentStatus::Authorized : PayPalPaymentStatus::Processing)
                ->withAuthorization($authorization)
                ->toArray(),
        );

        return $orderDetails;
    }

    private function authorizeApprovedOrder(
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        string $payPalOrderId,
        ?string $requestId,
    ): void {
        $this->approvedPayPalOrderUpdater->update($payment);

        try {
            $this->authorizeOrderApi->authorize($paymentMethod, $payPalOrderId, $requestId);
        } catch (PayPalApiConnectionException|PayPalApiServerException) {
        } catch (PayPalApiRequestException $exception) {
            if ([] === array_intersect($exception->getIssues(), self::ISSUES_LEAVING_THE_ORDER_TO_READ)) {
                throw $exception;
            }
        }
    }
}
