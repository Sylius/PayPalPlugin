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

namespace Sylius\PayPalPlugin\Completer;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

interface PayPalExpressOrderCompleterInterface
{
    // Callers must verify the payment amount before calling this; the correct check differs per flow.
    public function complete(OrderInterface $order, PaymentInterface $payment): void;
}
