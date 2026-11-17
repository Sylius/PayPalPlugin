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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final readonly class PreUpgradePayPalOrderChecker implements PreUpgradePayPalOrderCheckerInterface
{
    private const PAYPAL_ORDER_ACTIONS = [PaymentRequestInterface::ACTION_CAPTURE, PaymentRequestInterface::ACTION_AUTHORIZE];

    /** @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository */
    public function __construct(private PaymentRequestRepositoryInterface $paymentRequestRepository)
    {
    }

    public function isAwaitingPreUpgradePayPalOrder(PaymentInterface $payment): bool
    {
        if (
            BasePaymentInterface::STATE_PROCESSING !== $payment->getState() ||
            !PayPalPaymentDetails::fromPayment($payment)->hasPayPalOrderId()
        ) {
            return false;
        }

        return null === $this->paymentRequestRepository->findOneBy([
            'payment' => $payment,
            'action' => self::PAYPAL_ORDER_ACTIONS,
        ]);
    }
}
