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

namespace Sylius\PayPalPlugin\Verifier;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final class PaymentAmountVerifier implements PaymentAmountVerifierInterface
{
    public function verify(PaymentInterface $payment, ?PayPalOrderDetails $payPalOrderDetails = null): void
    {
        $totalAmount = $payPalOrderDetails?->amount() ?? $this->getPaymentAmountFromDetails($payment);

        if ($payment->getOrder()->getTotal() !== $totalAmount) {
            throw new PaymentAmountMismatchException();
        }
    }

    private function getPaymentAmountFromDetails(PaymentInterface $payment): int
    {
        return PayPalPaymentDetails::fromPayment($payment)->amount();
    }
}
