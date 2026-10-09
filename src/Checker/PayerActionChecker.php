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
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;

final readonly class PayerActionChecker implements PayerActionCheckerInterface
{
    public function isAwaitingPayerAction(PaymentInterface $payment): bool
    {
        if (PaymentInterface::STATE_PROCESSING !== $payment->getState()) {
            return false;
        }

        $details = PayPalPaymentDetails::fromPayment($payment);

        return
            null !== $details->payerActionUrl() &&
            null !== RedirectPaymentSource::tryFrom($details->paymentSource())
        ;
    }
}
