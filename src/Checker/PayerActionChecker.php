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
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;

final readonly class PayerActionChecker implements PayerActionCheckerInterface
{
    public function isAwaitingPayerAction(PaymentInterface $payment): bool
    {
        if (PaymentInterface::STATE_PROCESSING !== $payment->getState()) {
            return false;
        }

        $details = $payment->getDetails();

        return
            isset($details['payer_action_url']) &&
            null !== RedirectPaymentSource::tryFrom((string) ($details['payment_source'] ?? ''))
        ;
    }

    public function matchesPayerActionReturnNonce(PaymentInterface $payment, string $nonce): bool
    {
        return $this->matchesNonce($payment, 'payer_action_return_nonce', $nonce);
    }

    public function matchesPayerActionCancelNonce(PaymentInterface $payment, string $nonce): bool
    {
        return $this->matchesNonce($payment, 'payer_action_cancel_nonce', $nonce);
    }

    private function matchesNonce(PaymentInterface $payment, string $key, string $nonce): bool
    {
        $expectedNonce = $payment->getDetails()[$key] ?? null;

        if (!is_string($expectedNonce) || '' === $expectedNonce || '' === $nonce) {
            return false;
        }

        return hash_equals($expectedNonce, $nonce);
    }
}
