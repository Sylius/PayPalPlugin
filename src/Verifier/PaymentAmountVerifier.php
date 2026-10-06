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
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final class PaymentAmountVerifier implements PaymentAmountVerifierInterface
{
    public function verify(PaymentInterface $payment, array $paypalOrderDetails = []): void
    {
        $totalAmount = $this->getTotalPaymentAmountFromPaypal($payment, $paypalOrderDetails);

        if ($payment->getOrder()->getTotal() !== $totalAmount) {
            throw new PaymentAmountMismatchException();
        }
    }

    private function getTotalPaymentAmountFromPaypal(PaymentInterface $payment, array $paypalOrderDetails = []): int
    {
        if (empty($paypalOrderDetails)) {
            return $this->getPaymentAmountFromDetails($payment);
        }

        if (!isset($paypalOrderDetails['purchase_units']) || !is_array($paypalOrderDetails['purchase_units'])) {
            return 0;
        }

        $totalAmount = 0;

        foreach ($paypalOrderDetails['purchase_units'] as $unit) {
            $stringAmount = $unit['amount']['value'] ?? '0';
            $totalAmount += (int) round((float) $stringAmount * 100, 0);
        }

        return $totalAmount;
    }

    private function getPaymentAmountFromDetails(PaymentInterface $payment): int
    {
        return PayPalPaymentDetails::fromPayment($payment)->amount();
    }
}
