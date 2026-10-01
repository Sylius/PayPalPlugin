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

interface PaymentSettlementProcessorInterface
{
    /** @param array<string, mixed>|null $payPalOrderDetails */
    public function settle(PaymentInterface $payment, ?array $payPalOrderDetails = null): void;
}
