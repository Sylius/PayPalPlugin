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

namespace Sylius\PayPalPlugin\Repository\Query;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;

interface PaypalPaymentQueryInterface
{
    /** @throws PaymentNotFoundException */
    public function getForUpdateByOrderId(string $paypalOrderId): ?PaymentInterface;

    /** @throws PaymentNotFoundException */
    public function getForCancellationByOrderId(string $paypalOrderId): ?PaymentInterface;

    /** @throws PaymentNotFoundException */
    public function getForRefundingByOrderId(string $paypalOrderId): ?PaymentInterface;

    /** @throws PaymentNotFoundException */
    public function getForSettlementByOrderId(string $paypalOrderId): ?PaymentInterface;
}
