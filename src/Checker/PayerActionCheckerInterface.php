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

interface PayerActionCheckerInterface
{
    public function isAwaitingPayerAction(PaymentInterface $payment): bool;

    /** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
    public function matchesPayerActionReturnNonce(PaymentInterface $payment, string $nonce): bool;

    /** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
    public function matchesPayerActionCancelNonce(PaymentInterface $payment, string $nonce): bool;
}
